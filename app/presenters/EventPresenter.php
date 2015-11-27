<?php

/**
 * Kontroler na obsluhu požadavkù týkajících se událostí.
 * @author Daemon
 * @version 1.0
 * @created 05-XII-2013 1:36:06
 */
class EventPresenter extends BasePresenter
{
	/**
	 * Událost je reprezentována jednoznaèným ID, které je pøevzato od Google.
	 */
	private $EventId = NULL;


	/**
	 * Vložení nové události
	 */
	public function renderAddEvent()
	{
        try {
            if (!$this->getUser() || !$this->getUser()->isLoggedIn())
                $this->Redirect("Sign:default");
        } catch(Exception $e) { $this->Redirect("Sign:default"); }

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
            $this->Redirect("Sign:default");

        global $calendarApi;
        $this->template->event = null;
        $this->template->referer = "";

        try {
	        $this->template->event = $calendarApi->events->get($calendarId, $eventId);
	        $this->template->referer = $this->link('Calendar:viewCalendarEvents', $calendarId);
	        $this [ "updateEventForm" ] [ "summary" ] -> setDefaultValue ( $this -> template -> event -> summary );
	        $this [ "updateEventForm" ] [ "begin" ] -> setDefaultValue ( date('d. m. Y H:i:s', strtotime($this -> template -> event ->start->dateTime)));
	        $this [ "updateEventForm" ] [ "end" ] -> setDefaultValue ( date('d. m. Y H:i:s', strtotime($this -> template -> event ->end->dateTime)));
	        $this [ "updateEventForm" ] [ "description" ] -> setDefaultValue ( $this -> template -> event -> description );
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

		$form -> onSuccess [] = callback ( $this, "updateEventFormProcess" );

		return $form;
	}

	public function updateEventFormProcess ( $form )
	{
		$values = $form -> values;
		global $calendarApi;
		$event = $calendarApi->events->get( $values["calendarId"], $values["eventId"] );
		$event -> summary = $values [ "summary" ];
		$event -> description = $values [ "description" ];
		$begin = DateTime::createFromFormat('d. m. Y H:i:s', $values [ "begin" ] );
		$end   = DateTime::createFromFormat('d. m. Y H:i:s', $values [ "end" ] );
		$event -> start -> dateTime = date ( "Y-m-d\TH:i:sP", $begin -> getTimestamp () );
		$event -> end -> dateTime = date ( "Y-m-d\TH:i:sP", $end -> getTimestamp () );
		$calendarApi->events->update ($values["calendarId"], $values["eventId"], $event);
	
		$this -> flashMessage ( "Událost byla uložena." );
		$this -> redirect ( "Calendar:viewCalendarEvents", $values [ "calendarId" ] );
	}

}
?>
