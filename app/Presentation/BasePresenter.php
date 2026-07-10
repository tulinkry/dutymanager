<?php

namespace App\Presentation;

use Nette\Application\UI\Presenter;
use Nette\Security\SimpleIdentity;

/**
 * Base presenter for all application presenters.
 */
abstract class BasePresenter extends Presenter
{

	public function startup ()
	{
		parent::startup();

		// MOCK_GOOGLE_API skips real Google OAuth entirely (see MockGoogleCalendarService)
		// - auto-login here means every presenter's own isLoggedIn() check (including
		// SignPresenter's own "already logged in, redirect to Index" check) just works,
		// without touching SignPresenter or the router at all.
		if (filter_var(getenv('MOCK_GOOGLE_API'), FILTER_VALIDATE_BOOL) && !$this->getUser()->isLoggedIn()) {
			$this->getUser()->login(new SimpleIdentity('mock-user', null, ['email' => 'mock-user@example.com']));
		}

		$this->template->faviconIcon = 'calendar-' . sprintf('%02d', date('j')) . '.png';
		if(!$this->getSession()->hasSection('alerts')) {
	        $this->getSession('alerts')->alerts = array (
	            0 => "<strong>Věděli jste, že</strong> nyní lze klikem na řádek tabulky rozkliknout průběžné týdenní součty?",
	            1 => "<strong>Věděli jste, že</strong> DutyManager nově nabízí počítání mzdy i s daňovými parametry?",
	            2 => "<strong>Věděli jste, že</strong> nyní lze ceny v popisu události přičíst k ceně získané z hodinové taxy a délky události?",
	        );
	        $this->getSession('alerts')->setExpiration("+7 days");
		}
	}


	public function handleAlert( $alert_id ) {
		if($this->session->hasSection('alerts')) {
			if(array_key_exists($alert_id, $this->getSession("alerts")->alerts)) {
				unset($this->getSession("alerts")->alerts[$alert_id]);
			}
      $this->template->alerts = $this->getSession("alerts")->alerts;
		}
    
    

		$this->redrawControl('alerts');
		if(!$this->isAjax () ) {
			$this->redirect("this");
		}
	}

}
