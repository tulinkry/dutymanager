<?php

namespace App\Model\Google;

use Google\Client;
use Nette\Http\Session;
use Nette\Security\SimpleIdentity;

/**
 * Handles the Google OAuth2 login flow. Doesn't implement Nette\Security\Authenticator
 * (that interface expects username/password credentials) - SignPresenter logs the
 * resulting identity in directly via User::login($identity).
 */
class OAuthAuthenticator
{
	public function __construct(
		private Client $client,
		private Session $session,
	) {
	}

	public function authenticate(string $code): SimpleIdentity
	{
		$token = $this->client->fetchAccessTokenWithAuthCode($code);

		if (empty($token['access_token'])) {
			throw new \RuntimeException(sprintf(
				'Google did not return an access token: %s',
				$token['error_description'] ?? $token['error'] ?? json_encode($token)
			));
		}

		$this->session->getSection('google')->token = $token;

		return new SimpleIdentity($token['access_token'], null, $token);
	}

	public function getAuthAddress(): string
	{
		return $this->client->createAuthUrl();
	}
}
