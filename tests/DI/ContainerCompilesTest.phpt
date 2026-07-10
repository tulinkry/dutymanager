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
	$configurator->addStaticParameters(['env' => getenv()]);
	$configurator->addConfig(__DIR__ . '/../../app/config/config.neon');

	$container = $configurator->createContainer();

	Assert::true($container instanceof Nette\DI\Container);
	Assert::type(RouterFactory::class, $container->getService('routerFactory'));
	Assert::type(GoogleCalendarService::class, $container->getByType(GoogleCalendarService::class));
	Assert::type(OAuthAuthenticator::class, $container->getByType(OAuthAuthenticator::class));
});

test('config.mock.neon swaps in MockGoogleCalendarService', function () {
	putenv('GOOGLE_CLIENT_ID');
	putenv('GOOGLE_CLIENT_SECRET');
	putenv('GOOGLE_REDIRECT_URI');

	$tempDir = sys_get_temp_dir() . '/dutymanager-test-mock-' . getmypid();
	@mkdir($tempDir, 0777, true);

	$configurator = new Nette\Bootstrap\Configurator;
	$configurator->setDebugMode(false);
	$configurator->setTempDirectory($tempDir);
	$configurator->addStaticParameters(['env' => getenv()]);
	$configurator->addConfig(__DIR__ . '/../../app/config/config.neon');
	$configurator->addConfig(__DIR__ . '/../../app/config/config.mock.neon');

	// No real Google credentials anywhere (env vars unset entirely) - the container
	// must still compile, using config.mock.neon's own env.* fallback defaults, and
	// wire a working "google" service, since mock mode never touches the real
	// Google\Client/ClientFactory/OAuthAuthenticator services.
	$container = $configurator->createContainer();

	Assert::type(MockGoogleCalendarService::class, $container->getByType(GoogleCalendarService::class));
});

test('config.local.neon env.* values are not clobbered by addStaticParameters', function () {
	// Regression test for app/bootstrap.php: Configurator::createContainer() merges
	// addStaticParameters() in *last*, after every addConfig() file. Unsetting these
	// env vars entirely (not just blanking them) means getenv() genuinely omits the
	// keys, so Nette's recursive array-parameter merge combines config.local.neon's
	// own env.* overrides untouched instead of an empty string clobbering them.
	putenv('GOOGLE_CLIENT_ID');
	putenv('GOOGLE_CLIENT_SECRET');
	putenv('GOOGLE_REDIRECT_URI');

	$localConfigFile = sys_get_temp_dir() . '/dutymanager-config-local-' . getmypid() . '.neon';
	file_put_contents($localConfigFile, <<<NEON
		parameters:
			env:
				GOOGLE_CLIENT_ID: secret-from-config-local-neon
				GOOGLE_CLIENT_SECRET: another-secret-from-config-local-neon
				GOOGLE_REDIRECT_URI: https://example.com/check
		NEON);

	try {
		$tempDir = sys_get_temp_dir() . '/dutymanager-test-local-neon-' . getmypid();
		@mkdir($tempDir, 0777, true);

		$configurator = new Nette\Bootstrap\Configurator;
		$configurator->setDebugMode(false);
		$configurator->setTempDirectory($tempDir);
		$configurator->addStaticParameters(['env' => getenv()]);
		$configurator->addConfig(__DIR__ . '/../../app/config/config.neon');
		$configurator->addConfig($localConfigFile);

		$container = $configurator->createContainer();

		Assert::same('secret-from-config-local-neon', $container->getParameters()['env']['GOOGLE_CLIENT_ID']);
		Assert::same('another-secret-from-config-local-neon', $container->getParameters()['env']['GOOGLE_CLIENT_SECRET']);
		Assert::same('https://example.com/check', $container->getParameters()['env']['GOOGLE_REDIRECT_URI']);
	} finally {
		unlink($localConfigFile);
	}
});
