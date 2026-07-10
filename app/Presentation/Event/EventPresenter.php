<?php

namespace App\Presentation\Event;

use App\Presentation\BasePresenter;
use App\Model\Google\GoogleCalendarService;

/**
 * Kontroler na obsluhu požadavkù týkajících se událostí.
 * @author Daemon
 * @version 1.0
 * @created 05-XII-2013 1:36:06
 */
class EventPresenter extends BasePresenter
{
	public function __construct(
		private GoogleCalendarService $googleService,
	) {
		parent::__construct();
	}

	/**
	 * Vložení nové události
	 */
	public function renderAddEvent()
	{
        try {
            if (!$this->getUser() || !$this->getUser()->isLoggedIn())
                $this->redirect('Sign:default');
        } catch(\Exception $e) { $this->redirect('Sign:default'); }

		// render add ëvent form

		// on submit and validated
		// redirect to static page with some text
		// it is not necessarry to create another presenter method (i think :D)
	}

	/**
	 * Náhled jedné události s možností úpravy nebo smazání.
	 */
	public function renderViewEvent( $calendarId, $eventId )
	{
        if (!$this->getUser()->isLoggedIn())
            $this->redirect('Sign:default');

        $this->template->event = null;
        $this->template->referer = "";

        try {
	        $event = $this->googleService->getEvent($calendarId, $eventId);
	        $this->template->event = $event;
	        $this->template->referer = $this->link('Calendar:viewCalendarEvents', $calendarId);
	        $this [ "updateEventForm" ] [ "summary" ] -> setDefaultValue ( $event->getSummary() );
	        $this [ "updateEventForm" ] [ "begin" ] -> setDefaultValue ( date('d. m. Y H:i:s', strtotime($event->getStart()->getDateTime())));
	        $this [ "updateEventForm" ] [ "end" ] -> setDefaultValue ( date('d. m. Y H:i:s', strtotime($event->getEnd()->getDateTime())));
	        $this [ "updateEventForm" ] [ "description" ] -> setDefaultValue ( $event->getDescription() );
	        $this [ "updateEventForm" ] [ "calendarId" ] -> setDefaultValue ( $calendarId );
	        $this [ "updateEventForm" ] [ "eventId" ] -> setDefaultValue ( $eventId );

        } catch ( \Exception $e ) {
        	$this -> flashMessage ( "Nepodařilo se kontaktovat Google Kalendář, zkuste to prosím znovu za pár minut." );
        }


	}

	protected function createComponentUpdateEventForm ( $name )
	{
		$form = new \Nette\Application\UI\Form ( $this, $name );

		$form -> addText ( "summary", "Název" )
			  -> setAttribute ( "placeholder", "Zadejte název události" );
		$form -> addTextArea ( "description", "Popis" )
			  -> setAttribute ( "placeholder", "Zadejte popis události" );
		$form -> addText ( "begin", "Začátek" )
			  -> addRule ( $form::PATTERN, "Datum musí být ve formátu: dd. mm. yyyy hh:mm:ss", "^[0-9]{2}\. [0-9]{2}\. [0-9]{4} [0-9]{2}:[0-9]{2}:[0-9]{2}$")
			  -> setAttribute ( "placeholder", "dd. mm. yyyy hh:mm:ss" );
		$form -> addText ( "end", "Konec" )
			  -> addRule ( $form::PATTERN, "Datum musí být ve formátu: dd. mm. yyyy hh:mm:ss", "^[0-9]{2}\. [0-9]{2}\. [0-9]{4} [0-9]{2}:[0-9]{2}:[0-9]{2}$")
			  -> setAttribute ( "placeholder", "dd. mm. yyyy hh:mm:ss" );

		$form -> addHidden ( "calendarId" );
		$form -> addHidden ( "eventId" );

		$form -> addSubmit ( "update", "Uložit" );
		$form -> addSubmit ( "delete", "Smazat" )
			  -> setDisabled ();

		$form -> onSuccess [] = [ $this, "updateEventFormProcess" ];

		return $form;
	}

	public function updateEventFormProcess ( $form )
	{
		$values = $form -> getValues();
		$event = $this->googleService->getEvent( $values["calendarId"], $values["eventId"] );
		$event->setSummary( $values [ "summary" ] );
		$event->setDescription( $values [ "description" ] );
		$begin = \DateTime::createFromFormat('d. m. Y H:i:s', $values [ "begin" ] );
		$end   = \DateTime::createFromFormat('d. m. Y H:i:s', $values [ "end" ] );
		$event->getStart()->setDateTime( date ( "Y-m-d\TH:i:sP", $begin -> getTimestamp () ) );
		$event->getEnd()->setDateTime( date ( "Y-m-d\TH:i:sP", $end -> getTimestamp () ) );
		$this->googleService->updateEvent( $values["calendarId"], $values["eventId"], $event );

		$this -> flashMessage ( "Událost byla uložena." );
		$this -> redirect ( "Calendar:viewCalendarEvents", $values [ "calendarId" ] );
	}

}
