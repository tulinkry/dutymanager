<?php

/**
 * Base presenter for all application presenters.
 */
abstract class BasePresenter extends Nette\Application\UI\Presenter
{

	public function startup ()
	{
		parent::startup();
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
    
    

		$this->invalidateControl('alerts');
		if(!$this->isAjax () ) {
			$this->redirect("this");
		}
	}

}
