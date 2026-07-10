<?php

namespace App\Model\Google;

use Google\Client;
use Google\Service\Calendar;
use Nette\Http\Session;

class ClientFactory
{
	public function __construct(
		private string $clientId,
		private string $clientSecret,
		private string $redirectUri,
		private Session $session,
	) {
	}

	public function create(): Client
	{
		$client = new Client();
		$client->setApplicationName('DutyManager');
		$client->setClientId($this->clientId);
		$client->setClientSecret($this->clientSecret);
		$client->setRedirectUri($this->redirectUri);
		$client->addScope(Calendar::CALENDAR);
		$client->setAccessType('offline');
		$client->setPrompt('consent');

		$section = $this->session->getSection('google');
		$token = $section->token ?? null;

		if ($token) {
			$client->setAccessToken($token);

			if ($client->isAccessTokenExpired()) {
				$refreshToken = $client->getRefreshToken() ?: ($token['refresh_token'] ?? null);

				if ($refreshToken) {
					$client->fetchAccessTokenWithRefreshToken($refreshToken);
					$newToken = $client->getAccessToken();
					if (empty($newToken['refresh_token'])) {
						$newToken['refresh_token'] = $refreshToken;
					}
					$section->token = $newToken;
				}
			}
		}

		return $client;
	}
}
