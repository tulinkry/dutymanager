<?php


use Nette\Application\UI;

/**
 * Základní stránka s výbìrem kalendáøe
 * @author Daemon
 * @version 1.0
 * @created 05-XII-2013 1:36:06
 */
class IndexPresenter extends BasePresenter
{

	/**
	 * Seznam všech uživatelových kalendáøù
	 */
	public function renderDefault()
	{
        if (!$this->getUser()->isLoggedIn())
            $this->Redirect("Sign:default");

		$this->template->calendars = null;

        global $calendarApi;
		try {
	        $this->template->calendars =  $calendarApi->calendarList->listCalendarList();
        } catch ( \Exception $e ) {
            $this -> flashMessage ( "Nepodařilo se kontaktovat Google Kalendář, zkuste to prosím znovu za pár minut." );
        }
	}
}
?>
