<?php

use Nette\Security,
	Nette\Utils\Strings;


/**
 * Users authenticator.
 */
class OAuthAuthenticator extends Nette\Object implements Security\IAuthenticator
{
	/**
	 * Performs an authentication.
	 * @return Nette\Security\Identity
	 * @throws Nette\Security\AuthenticationException
	 */
	public function authenticate(array $credentials)
	{
        list($code) = $credentials;
		if (!isset($code)) {
			throw new Security\AuthenticationException('Login Failed.', self::IDENTITY_NOT_FOUND);
        }
        global $client;
        $client->authenticate($code);
        $_SESSION['access_token'] = $client->getAccessToken();
        $data = json_decode($client->getAccessToken(), true);
        return new Nette\Security\Identity($data['access_token'], NULL, $data);
	}
    
    static public function getAuthAddress()
    {
        global $client;
        return $client->createAuthUrl();
    }

}
