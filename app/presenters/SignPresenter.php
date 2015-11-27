<?php

use Nette\Http\IResponse;


/**
 * Index presenter.
 */
class SignPresenter extends BasePresenter
{

	public function renderDefault()
	{
        if ($this->getUser()->isLoggedIn())
            $this->Redirect('Index:default');
		$this->template->button = array ( "secret_link" => OAuthAuthenticator::getAuthAddress( 'http://localhost'. $this->link('verify')), "label" => "Přihlásit se" );
	}

	public function renderVerify()
	{
		// this method should receive some link to google
		// lead the user to follow it, autorizate and go back to our application
		// then he is redirected with new google token to
		// viewCalendar

        if (!isset($_GET['code']) || empty($_GET['code']))
            $this->Redirect("Sign:default");
        else
        {
            try
            {
            
            
                $this->getUser()->setAuthenticator(new OAuthAuthenticator);
                $this->getUser()->login($_GET['code']);
                $this->Redirect('Index:default');
            } catch (Nette\Security\AuthenticationException $e)
            {
                $this->error($e->getMessage(), IResponse::S403_FORBIDDEN);
            }
        }
	}
    
    public function renderLogout()
    {
        session_destroy();
        $this->getUser()->logout(true);
        $this->flashMessage('Byl jste úspěšně odhlášen!');
        $this->redirect("Sign:default");
    }

}
