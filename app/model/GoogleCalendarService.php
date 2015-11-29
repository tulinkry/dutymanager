<?php

namespace Tulinkry\Google;

use iCalDate;
use Event;

class GoogleCalendarService {
	
	public function getCalendars ()
	{
        global $calendarApi;
        return $calendarApi->calendarList->listCalendarList();
	}

	public function getCalendar ( $id ) 
	{
		global $calendarApi;
		return $calendarApi->calendars->get($id);
	}

	protected function preprocessEvents($id, $by)
	{
    	// default one whole month
        $from_time = new iCalDate( strtotime( date ( 'Y-m' ) . "-01 00:00:00" ) );
        $to_time = new iCalDate ( strtotime ( date ('Y-m-') . $from_time->DaysInMonth( date('m'), date('Y') )." 23:59:59" ));

        if ( $by ) {
            $from_time = new iCalDate ( strtotime($by->from_time));
            $to_time = new iCalDate ( strtotime($by->to_time));
            $price = 0;
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

    
        global $calendarApi;
        $events = array ();
        $optParams = array ('timeMin' => $from_bounded_time->RenderGMT(DATE_ATOM),
                            'timeMax' => $to_bounded_time->RenderGMT(DATE_ATOM) );
        $googleEvents = $calendarApi->events->listEvents($id, $optParams);

        while ( 1 )
        {
            //print_r ( $googleEvents );
            foreach ( $googleEvents->getItems () as $key => $event )
            {
                if ( !isset( $event->start) || ! isset ( $event -> end ) )
                {
                    // unset ( $googleEvents [ $key ] );
                    // some bad event
                    continue;
                }
                
                if ( $event -> recurrence == null  )
                {
                  if ( $event -> recurringEventId == null )
                  {
                    $this -> addEvent ( $event, $from_bounded_time, $to_bounded_time, $events );
                  }
                }
                else
                {
                    // reccurrence event
                    // add all reccurrences
                    $recEvents = $calendarApi->events->instances( $id, $event->id, $optParams );
                    while ( 1 )
                    {
                        //print_r ( $recEvents );
                        foreach ( $recEvents->getItems() as $key => $event )
                            $this -> addEvent ( $event, $from_bounded_time, $to_bounded_time, $events );

                        $pgTkn = $recEvents->getNextPageToken ();
                        if ( !$pgTkn )
                        	break;
                        $optP = array_merge( $optParams, [ "pageToken" => $pgTkn ] );
                        $recEvents = $calendarApi->events->instances( $id, $event->id, $optParams );

                    }

                }
            }
            $pageToken = $googleEvents -> getNextPageToken ();
            if ( ! $pageToken )
            	break;
            $optParams = array_merge( $optParams, [ "pageToken" => $pageToken ] );
            $googleEvents = $calendarApi->events->listEvents($id, $optParams);
        }


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

            /*
            if (strlen(trim($by->from_bounded_time)))
                foreach ($events as $key => $event)
                    if (strtotime($event->m_Start->Render()) < strtotime($from_bounded_time->Render()))
                        unset($events[$key]);

            if (strlen(trim($by->to_bounded_time)))
                foreach ($events as $key => $event)
                    if (strtotime($event->m_End->Render ()) > strtotime($to_bounded_time->Render()))
                        unset($events[$key]);
            */

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
                            //$event->description = //floatval($by->value_container->price) *
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


        $r = new EventContainer($events);
        $r -> start = $from_time;
        $r -> end = $to_time;
        
        return $r;
		
	}	



	public function getEvents ( $id, $by = null )
    {
        $events = $this->preprocessEvents($id,$by);

        
        $week_events = clone $events;
        $events->filter();

        foreach ($events->events as $key => $event)
        {
            $event->m_Description = substr ( $event->m_Description, 0, 10 ); // short the description if neccessary
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

        $this->convertEvent ( $event );

        $ev = new Event ( $event->start, $event->end );
        $ev -> m_Id = $event -> id;
        $ev -> m_Summary = $event -> summary;
        $ev -> m_Price = 0;
        $ev -> m_Description = $event -> description;
        $ev -> m_Duration = (strtotime($ev->m_End->Render ()) - strtotime($ev->m_Start->Render())) / 3600;
        if ( $ev -> m_Start -> GreaterThan ( $from_time ) && $ev -> m_Start -> LessThan ( $to_time ) )
            // if event is between two times
            // add him to the array
            $array[] = $ev;
    }
    protected function convertEvent ( &$event )
    {
        // converts the start and end of the event to the 
        // iCalDate format
        // if some event doesnt have the datetime form of date
        // and has only the date form, it converts it

        if ( isset($event->start->dateTime) )
        {
            $event -> start = new iCalDate ( strtotime($event -> start -> dateTime) );
        } else
        {
            $event -> start = new iCalDate ( strtotime($event->start->date."T00:00:00") );
        }
        if ( isset($event->end->dateTime) )
        {
            $event -> end = new iCalDate (strtotime( $event -> end -> dateTime) );
        } else
        {
            $event -> end = new iCalDate ( strtotime($event->end->date."T00:00:00") );
            //$event -> end -> addDays ( 1 );
        }
    }    

    public static function cmpfunc ( $a, $b )
    {
        // compare function of two dates
        return (strtotime($a->m_Start->Render()) - strtotime($b->m_Start->Render()));
    }
}