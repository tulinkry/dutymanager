<?php declare(strict_types=1);

use Tester\Assert;
use App\Core\RouterFactory;
use App\Model\Google\GoogleCalendarService;
use App\Model\Google\MockGoogleCalendarService;
use App\Model\Google\OAuthAuthenticator;

require __DIR__ . '/../bootstrap.php';

test('DI container compiles and wires the expected services', function () {
	putenv('GOOGLE_CLIENT_ID=test-client-id');
	putenv('GOOGLE_CLIENT_SECRET=test-client-secret');
	putenv('GOOGLE_REDIRECT_URI=http://localhost/check');

	$tempDir = sys_get_temp_dir() . '/dutymanager-test-' . getmypid();
	@mkdir($tempDir, 0777, true);

	$configurator = new Nette\Bootstrap\Configurator;
	$configurator->setDebugMode(false);
	$configurator->setTempDirectory($tempDir);
	$configurator->addStaticParameters([
		'googleClientId' => (string) getenv('GOOGLE_CLIENT_ID'),
		'googleClientSecret' => (string) getenv('GOOGLE_CLIENT_SECRET'),
		'googleRedirectUri' => (string) getenv('GOOGLE_REDIRECT_URI'),
	]);
	$configurator->addConfig(__DIR__ . '/../../app/config/config.neon');

	$container = $configurator->createContainer();

	Assert::true($container instanceof Nette\DI\Container);
	Assert::type(RouterFactory::class, $container->getService('routerFactory'));
	Assert::type(GoogleCalendarService::class, $container->getByType(GoogleCalendarService::class));
	Assert::type(OAuthAuthenticator::class, $container->getByType(OAuthAuthenticator::class));
});

test('config.mock.neon swaps in MockGoogleCalendarService', function () {
	putenv('GOOGLE_CLIENT_ID=');
	putenv('GOOGLE_CLIENT_SECRET=');
	putenv('GOOGLE_REDIRECT_URI=');

	$tempDir = sys_get_temp_dir() . '/dutymanager-test-mock-' . getmypid();
	@mkdir($tempDir, 0777, true);

	$configurator = new Nette\Bootstrap\Configurator;
	$configurator->setDebugMode(false);
	$configurator->setTempDirectory($tempDir);
	$configurator->addStaticParameters([
		'googleClientId' => (string) getenv('GOOGLE_CLIENT_ID'),
		'googleClientSecret' => (string) getenv('GOOGLE_CLIENT_SECRET'),
		'googleRedirectUri' => (string) getenv('GOOGLE_REDIRECT_URI'),
	]);
	$configurator->addConfig(__DIR__ . '/../../app/config/config.neon');
	$configurator->addConfig(__DIR__ . '/../../app/config/config.mock.neon');

	// No real Google credentials at all - the container must still compile and
	// wire a working "google" service, since mock mode never touches the real
	// Google\Client/ClientFactory/OAuthAuthenticator services.
	$container = $configurator->createContainer();

	Assert::type(MockGoogleCalendarService::class, $container->getByType(GoogleCalendarService::class));
});
