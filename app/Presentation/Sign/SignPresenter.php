<?php

namespace App\Presentation\Sign;

use App\Presentation\BasePresenter;
use App\Model\Google\OAuthAuthenticator;
use Nette\Http\IResponse;
use Tracy\Debugger;
use Tracy\ILogger;


/**
 * Index presenter.
 */
class SignPresenter extends BasePresenter
{

	public function __construct(
		private OAuthAuthenticator $oauthAuthenticator,
	) {
		parent::__construct();
	}

	public function renderDefault()
	{
        if ($this->getUser()->isLoggedIn())
            $this->redirect('Index:default');
		$this->template->button = array ( "secret_link" => $this->oauthAuthenticator->getAuthAddress(), "label" => "Přihlásit se" );
	}

	public function renderVerify()
	{
		// this method should receive some link to google
		// lead the user to follow it, autorizate and go back to our application
		// then he is redirected with new google token to
		// viewCalendar

        if (!isset($_GET['code']) || empty($_GET['code']))
            $this->redirect('Sign:default');

        try
        {
            $identity = $this->oauthAuthenticator->authenticate($_GET['code']);
        } catch (\Throwable $e)
        {
            Debugger::log($e, ILogger::EXCEPTION);
            $this->error(sprintf('%s: %s', $e::class, $e->getMessage()), IResponse::S403_FORBIDDEN);
        }

        $this->getUser()->login($identity);
        $this->redirect('Index:default');
	}

    public function renderLogout()
    {
        $this->getUser()->logout(true);
        $this->flashMessage('Byl jste úspěšně odhlášen!');
        $this->redirect("Sign:default");
    }

}
