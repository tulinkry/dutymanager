<?php declare(strict_types=1);

use Tester\Assert;
use App\Model\Calendar\CalendarSearchFacade;
use App\Model\Export\EmptyDayFiller;
use App\Forms\ExportFormFactory;
use App\Presentation\Export\ExportPresenter;

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../fixtures/FakeGoogleCalendarService.php';
require __DIR__ . '/../fixtures/presenter-test-helpers.php';

// Regression test for a real bug: visiting the Export page always 500'd with
// "Cannot access protected property App\Model\Export\Csv::$name". Removing
// Nette\Object from Exporter.php (it no longer exists in current Nette) also removed
// the magic property access that used to let ExportPresenter reach $exporter->name
// directly - see app/Presentation/Export/ExportPresenter.php's createComponentExportForm(),
// which must use the existing public getName() method instead.
Kdyby\Replicator\Container::register();

test('exportForm renders in isolation with the hidden _do signal field', function () {
	$fake = new FakeGoogleCalendarService();
	$presenter = new ExportPresenter($fake, new CalendarSearchFacade(), new EmptyDayFiller(), new ExportFormFactory());
	injectFakePresenterDependencies($presenter);

	// ExportPresenter::startup() reads calendar_id/form_data from the "search" session
	// section - Nette's own session must actually be started first, or its lazy
	// auto-start (triggered by BasePresenter's alerts section) will overwrite this
	// section with an empty one before startup() ever reads it.
	$presenter->getSession()->start();
	$search = $presenter->getSession('search');
	$search['calendar_id'] = 'some-calendar-id';
	$search['form_data'] = (object) ['from_time' => '2026-01-01 00:00:00', 'to_time' => '2026-01-31 23:59:59'];

	$presenter->startup();

	$form = $presenter->getComponent('exportForm');
	$form->getElementPrototype()->action = '#';
	ob_start();
	$form->render();
	$html = ob_get_clean();

	Assert::contains('Exportovat', $html);
	Assert::contains('name="_do"', $html);
});

// Regression test for two more real bugs, only visible when the *actual* .latte
// template is compiled and rendered (not just the form component in isolation):
// - {input ..., 'data-live-search': 'true'} used a quoted key with the new colon
//   shorthand, which Latte only allows for bare identifiers; quoted keys need the
//   classic 'key' => value arrow instead.
// - {$form['fields']->currentGroup->...} and ->containers were Nette 2.x-era magic
//   properties on form/replicator containers; current Nette only exposes these via
//   getCurrentGroup()/getContainers().
test('Export/default.latte compiles and renders the full form', function () {
	$fake = new FakeGoogleCalendarService();
	$presenter = new ExportPresenter($fake, new CalendarSearchFacade(), new EmptyDayFiller(), new ExportFormFactory());
	injectFakePresenterDependencies($presenter);

	$presenter->getSession()->start();
	$search = $presenter->getSession('search');
	$search['calendar_id'] = 'some-calendar-id';
	$search['form_data'] = (object) ['from_time' => '2026-01-01 00:00:00', 'to_time' => '2026-01-31 23:59:59'];

	$presenter->startup();

	$html = renderPresenterTemplate($presenter, __DIR__ . '/../../app/Presentation/Export/default.latte');

	Assert::contains('Exportovat', $html);
	Assert::contains('name="_do"', $html);
	Assert::contains('<legend>', $html);
});
