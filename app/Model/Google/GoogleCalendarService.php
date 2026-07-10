<?php

namespace App\Model\Google;

use App\Model\Calendar\iCalDate;
use App\Model\Calendar\Event;
use App\Model\Calendar\EventContainer;
use Google\Service\Calendar;

class GoogleCalendarService {

	public function __construct(
		private Calendar $calendarApi,
	) {
	}

	public function getCalendars ()
	{
        return $this->calendarApi->calendarList->listCalendarList();
	}

	public function getCalendar ( $id )
	{
		return $this->calendarApi->calendars->get($id);
	}

	public function getEvent ( $calendarId, $eventId )
	{
		return $this->calendarApi->events->get($calendarId, $eventId);
	}

	public function updateEvent ( $calendarId, $eventId, \Google\Service\Calendar\Event $event )
	{
		return $this->calendarApi->events->update($calendarId, $eventId, $event);
	}

	protected function preprocessEvents($id, $by)
	{
        [$from_time, $to_time, $from_bounded_time, $to_bounded_time] = $this->resolveTimeRange($by);

        $events = $this->fetchRawEvents($id, $from_bounded_time, $to_bounded_time);
        $events = $this->applyFilters($events, $by);

        $r = new EventContainer($events);
        $r -> start = $from_time;
        $r -> end = $to_time;

        return $r;

	}

	/**
	 * Resolves the [from, to] range requested by the search form (or the current
	 * whole month by default), plus a week-rounded variant used for the actual
	 * Google API query when the "week_summary" option is set.
	 *
	 * @return array{0: iCalDate, 1: iCalDate, 2: iCalDate, 3: iCalDate}
	 */
	protected function resolveTimeRange($by): array
	{
    	// default one whole month
        $from_time = new iCalDate( strtotime( date ( 'Y-m' ) . "-01 00:00:00" ) );
        $to_time = new iCalDate ( strtotime ( date ('Y-m-') . $from_time->DaysInMonth( date('m'), date('Y') )." 23:59:59" ));

        if ( $by ) {
            $from_time = new iCalDate ( strtotime($by->from_time));
            $to_time = new iCalDate ( strtotime($by->to_time));
        }

        $from_bounded_time = $from_time;
        $to_bounded_time = $to_time;

        if($by && isset($by->week_summary) && $by->week_summary) {
            // round interval to whole weeks
            $from_bounded_time = strtotime ( "-1 Monday", $from_time->_epoch );
            $from_bounded_time = new iCalDate ( strtotime ( date ("Y-m-d", $from_bounded_time)." 00:00:00" ) );
            $to_bounded_time = strtotime ( "+1 Sunday", $to_time->_epoch );
            $to_bounded_time = new iCalDate ( strtotime ( date ("Y-m-d", $to_bounded_time)." 23:59:59" ) );
        }

        return [$from_time, $to_time, $from_bounded_time, $to_bounded_time];
	}

	/**
	 * Fetches events (including recurrence instances) from the real Google Calendar
	 * API within the given bounds, converted to the app's own Event value objects.
	 *
	 * @return Event[]
	 */
	protected function fetchRawEvents($id, $from_bounded_time, $to_bounded_time): array
	{
        $events = array ();
        $optParams = array ('timeMin' => $from_bounded_time->RenderGMT(DATE_ATOM),
                            'timeMax' => $to_bounded_time->RenderGMT(DATE_ATOM) );
        $googleEvents = $this->calendarApi->events->listEvents($id, $optParams);

        while ( 1 )
        {
            foreach ( $googleEvents->getItems () as $key => $event )
            {
                if ( ! $event->getStart() || ! $event->getEnd() )
                {
                    // some bad event
                    continue;
                }

                if ( $event->getRecurrence() === null )
                {
                  if ( $event->getRecurringEventId() === null )
                  {
                    $this -> addEvent ( $event, $from_bounded_time, $to_bounded_time, $events );
                  }
                }
                else
                {
                    // reccurrence event
                    // add all reccurrences
                    $recEvents = $this->calendarApi->events->instances( $id, $event->getId(), $optParams );
                    while ( 1 )
                    {
                        foreach ( $recEvents->getItems() as $key => $event )
                            $this -> addEvent ( $event, $from_bounded_time, $to_bounded_time, $events );

                        $pgTkn = $recEvents->getNextPageToken ();
                        if ( !$pgTkn )
                        	break;
                        $optP = array_merge( $optParams, [ "pageToken" => $pgTkn ] );
                        $recEvents = $this->calendarApi->events->instances( $id, $event->getId(), $optParams );

                    }

                }
            }
            $pageToken = $googleEvents -> getNextPageToken ();
            if ( ! $pageToken )
            	break;
            $optParams = array_merge( $optParams, [ "pageToken" => $pageToken ] );
            $googleEvents = $this->calendarApi->events->listEvents($id, $optParams);
        }

        return $events;
	}

	/**
	 * Applies the search form's name/price filtering and pricing calculation to an
	 * already-fetched pool of events. Shared by the real Google-backed service and
	 * MockGoogleCalendarService so both filter/price events identically.
	 *
	 * @param Event[] $events
	 * @return Event[]
	 */
	protected function applyFilters(array $events, $by): array
	{
        if ($by)
        {

            if (strlen($by->name_container->text_match))
                switch (intval($by->name_container->match_type))
                {
                    case 0: // RegExp
                        foreach ($events as $key => $event)
                            if (preg_match('/' . $by->name_container->text_match . '/', $event->m_Summary) !== 1)
                                unset($events[$key]);
                        break;
                    case 1: // Exact Match
                        foreach ($events as $key => $event)
                            if ($by->name_container->text_match != $event->m_Summary)
                                unset($events[$key]);
                        break;
                }

            switch (intval($by->value_container->price_type))
            {
                case 0: // Only Description
                    foreach ($events as $key => $event)
                        if (is_null($event->m_Description) || !strlen($event->m_Description))
                            unset($events[$key]);
                        else
                            $event->m_Price = floatval( $event->m_Description );
                     break;
                case 1: // Only paushal
                    if (is_null($by->value_container->price))
                        break;
                    foreach ($events as $key => $event)
                        if (!is_null($event->m_Description))
                            unset($events[$key]);
                        else
                        {
                            $event->m_Price = (strtotime($event->m_End->Render ()) - strtotime($event->m_Start->Render())) / 3600;
                            if ( $by->workmode )
                            {
                                // work pause every 6 hours
                                $event->m_Price -= floor($event->m_Price / 6) * 0.5; // work pause
                            }
                            $event->m_Duration = $event -> m_Price;
                            $event->m_Price *= floatval($by->value_container->price);
                            $event->m_Price = (int)$event->m_Price;
                        }
                    break;
                case 2:
                    if (is_null($by->value_container->price))
                        break;
                    foreach ($events as $key => $event)
                        if (is_null($event->m_Description))
                        {
                            $event->m_Price = (strtotime($event->m_End->Render ()) - strtotime($event->m_Start->Render())) / 3600;
                            if ( $by->workmode )
                            {
                                // work pause every 6 hours
                                $event->m_Price -= floor($event->m_Price / 6) * 0.5; // work pause
                            }
                            $event->m_Duration = $event -> m_Price;
                            $event->m_Price *= floatval($by->value_container->price);
                            $event->m_Price = (int)$event->m_Price;
                        }
                        else
                            $event->m_Price = floatval( $event->m_Description );
                    break;
                case 3: // Combined with adding
                    if (is_null($by->value_container->price))
                        break;
                    foreach ($events as $key => $event) {
                        $event->m_Price = (strtotime($event->m_End->Render ()) - strtotime($event->m_Start->Render())) / 3600;
                        if ( $by->workmode )
                        {
                            // work pause every 6 hours
                            $event->m_Price -= floor($event->m_Price / 6) * 0.5; // work pause
                        }
                        $event->m_Duration = $event -> m_Price;
                        $event->m_Price *= floatval($by->value_container->price);
                        $event->m_Price = (int)$event->m_Price;

                        if(!is_null($event->m_Description))
                            $event->m_Price += floatval( $event->m_Description );
                    }

                    break;
            }

        }

        return $events;
	}



	public function getEvents ( $id, $by = null )
    {
        $events = $this->preprocessEvents($id,$by);


        $week_events = clone $events;
        $events->filter();

        foreach ($events->events as $key => $event)
        {
            $event->m_Description = substr ( (string) $event->m_Description, 0, 10 ); // short the description if neccessary
        }

        if($by && $by->taxes) {
            $tax = array ( "values" => $by->taxes_container,
                           "params" => $by->tax_params );
            $events->applyTax($tax);
            $week_events->applyTax($tax);
        }

        $events->applySort(__CLASS__."::cmpfunc");

        return [ "events" => $events, "week_events" => $week_events ];
    }

    protected function addEvent ( $event, $from_time, $to_time, &$array )
    {
        // check if it is recursive
        // add all occurencces between two dates

        [$start, $end] = $this->convertEventDates ( $event );

        $ev = new Event ( $start, $end );
        $ev -> m_Id = $event -> getId();
        $ev -> m_Summary = $event -> getSummary();
        $ev -> m_Price = 0;
        $ev -> m_Description = $event -> getDescription();
        $ev -> m_Duration = (strtotime($ev->m_End->Render ()) - strtotime($ev->m_Start->Render())) / 3600;
        if ( $ev -> m_Start -> GreaterThan ( $from_time ) && $ev -> m_Start -> LessThan ( $to_time ) )
            // if event is between two times
            // add him to the array
            $array[] = $ev;
    }

    protected function convertEventDates ( $event )
    {
        // converts the start and end of the event to the
        // iCalDate format
        // if some event doesnt have the datetime form of date
        // and has only the date form, it converts it

        $start = $event->getStart();
        $startValue = $start->getDateTime() ?: ($start->getDate() . "T00:00:00");

        $end = $event->getEnd();
        $endValue = $end->getDateTime() ?: ($end->getDate() . "T00:00:00");

        return [ new iCalDate ( strtotime ( $startValue ) ), new iCalDate ( strtotime ( $endValue ) ) ];
    }

    public static function cmpfunc ( $a, $b )
    {
        // compare function of two dates
        return (strtotime($a->m_Start->Render()) - strtotime($b->m_Start->Render()));
    }
}
