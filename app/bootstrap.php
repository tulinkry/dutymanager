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

// Whole environment as one parameter (see %env.GOOGLE_CLIENT_ID% etc. in config.neon).
// getenv() only returns keys that actually exist, so on plain FTP hosting (no real
// server env vars) this simply omits them - letting config.local.neon's own
// env.* values merge in untouched instead of being clobbered by an empty string.
$configurator->addStaticParameters(['env' => getenv()]);

// Create Dependency Injection container from config.neon file
$configurator->addConfig(__DIR__ . '/config/config.neon');
if (filter_var(getenv('MOCK_GOOGLE_API'), FILTER_VALIDATE_BOOL)) {
	// Runs the app with a mocked Google service (deterministic demo calendars/events)
	// and skips login entirely (see BasePresenter::startup()) - no Google account or
	// OAuth credentials needed at all.
	$configurator->addConfig(__DIR__ . '/config/config.mock.neon');
}
if (is_file($localConfigFile = __DIR__ . '/config/config.local.neon')) {
	$configurator->addConfig($localConfigFile);
}
$container = $configurator->createContainer();

set_time_limit( 0 );

return $container;
