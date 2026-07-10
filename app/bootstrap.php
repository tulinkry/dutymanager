<?php


// Load Composer-generated autoloader
require __DIR__ . '/../vendor/autoload.php';

$configurator = new Nette\Bootstrap\Configurator;

// Enable Tracy for error visualisation & logging
$configurator->enableTracy(__DIR__ . '/../log');

if (filter_var(getenv('APP_DEBUG'), FILTER_VALIDATE_BOOL)) {
	// Docker requests never auto-detect as "local" - forces debug mode so Latte
	// recompiles on template changes instead of serving a stale cache.
	$configurator->setDebugMode(true);
}

// Specify folder for cache
$configurator->setTempDirectory(__DIR__ . '/../temp');

// Google OAuth credentials come from the environment (docker-compose.yml), never from source.
$configurator->addStaticParameters([
	'googleClientId' => (string) getenv('GOOGLE_CLIENT_ID'),
	'googleClientSecret' => (string) getenv('GOOGLE_CLIENT_SECRET'),
	'googleRedirectUri' => (string) getenv('GOOGLE_REDIRECT_URI'),
]);

// Create Dependency Injection container from config.neon file
$configurator->addConfig(__DIR__ . '/config/config.neon');
if (filter_var(getenv('MOCK_GOOGLE_API'), FILTER_VALIDATE_BOOL)) {
	// Runs the app with a mocked Google service (deterministic demo calendars/events)
	// and skips login entirely (see BasePresenter::startup()) - no Google account or
	// OAuth credentials needed at all.
	$configurator->addConfig(__DIR__ . '/config/config.mock.neon');
}
if (is_file(__DIR__ . '/config/config.local.neon')) {
	$configurator->addConfig(__DIR__ . '/config/config.local.neon');
}
$container = $configurator->createContainer();

set_time_limit( 0 );

return $container;
