<?php

namespace App\Presentation\Index;

use App\Presentation\BasePresenter;
use App\Model\Google\GoogleCalendarService;
use Nette\Application\UI;

/**
 * Základní stránka s výbìrem kalendáøe
 * @author Daemon
 * @version 1.0
 * @created 05-XII-2013 1:36:06
 */
class IndexPresenter extends BasePresenter
{
	public function __construct(
		private GoogleCalendarService $googleService,
	) {
		parent::__construct();
	}

	/**
	 * Seznam všech uživatelových kalendáøù
	 */
	public function renderDefault()
	{
        if (!$this->getUser()->isLoggedIn())
            $this->redirect('Sign:default');

		$this->template->calendars = null;

		try {
	        $this->template->calendars =  $this->googleService->getCalendars();
        } catch ( \Exception $e ) {
            $this -> flashMessage ( "Nepodařilo se kontaktovat Google Kalendář, zkuste to prosím znovu za pár minut." );
        }
	}
}
