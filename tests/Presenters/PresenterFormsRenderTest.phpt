<?php declare(strict_types=1);

use Tester\Assert;
use App\Model\Calendar\CalendarSearchFacade;
use App\Forms\SearchFormFactory;
use App\Model\Export\EmptyDayFiller;
use App\Forms\ExportFormFactory;
use App\Presentation\Calendar\CalendarPresenter;
use App\Presentation\Export\ExportPresenter;
use App\Presentation\Event\EventPresenter;
use App\Model\Google\GoogleCalendarService;

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../fixtures/presenter-test-helpers.php';

// In the real app this is registered once via config.neon's `extensions: replicator:
// Kdyby\Replicator\DI\ReplicatorExtension`, wired up when the DI container compiles.
// This test bypasses the container entirely, so it has to register it directly.
Kdyby\Replicator\Container::register();

// Renders a presenter's component to a string the same way a real request would
// (via {control xxx} in a template), without needing a live Google account or
// an HTTP request context. This is what catches things like a form-renderer
// method signature that no longer matches its parent class, or a form success
// handler wired up with the removed Nette\Object method-as-property magic -
// both only actually blow up once the form is *rendered*, not at DI-container-compile time.
function renderComponent(string $componentName, Nette\Application\UI\Presenter $presenter): string
{
	$component = $presenter->getComponent($componentName);
	if ($component instanceof Nette\Application\UI\Form) {
		// Avoids needing a real Router/PresenterFactory just to resolve the form's
		// auto-generated action link - this test only cares that rendering doesn't blow up.
		$component->getElementPrototype()->action = '#';
	}
	ob_start();
	$component->render();
	return ob_get_clean();
}

function createGoogleServiceStub(): GoogleCalendarService
{
	return new GoogleCalendarService(new Google\Service\Calendar(new Google\Client()));
}

// Every Nette\Application\UI\Form must render a hidden "_do" field identifying itself
// as the submit target - without it, the presenter can never recognize a POST back to
// this page as an actual submission of this form (onSuccess silently never fires, no
// error, it just looks like the form does nothing). This was a real, previously-shipped
// bug: Tulinkry\Forms\Form::render() skipped Nette\Forms\Form::fireRenderEvents(), which
// is what triggers Nette\Application\UI\Form::beforeRender() to inject that field.
function assertHasSubmitSignalField(string $html, string $formName): void
{
	Assert::contains('name="_do"', $html, "$formName must render the hidden _do submit-signal field");
	Assert::contains("$formName-submit", $html, "$formName's _do field must identify this exact form");
}

test('CalendarPresenter searchForm renders with the submit signal field', function () {
	$presenter = new CalendarPresenter(createGoogleServiceStub(), new CalendarSearchFacade(), new SearchFormFactory(new CalendarSearchFacade()));
	// createComponentSearchForm() reads/writes the "search" session section to
	// restore/save the form's last submitted values - needs a real Session service.
	injectFakePresenterDependencies($presenter);
	$presenter->getSession()->start();
	$html = renderComponent('searchForm', $presenter);

	// Bootstrap markup (form-group/col-sm-*) now lives in viewCalendarEvents.latte's
	// hand-written n:name markup, not in the form component itself - rendering the
	// component directly (bypassing the template) falls back to Nette's plain default
	// renderer. See CalendarPrintRenderTest.phpt for a template-level render.
	Assert::contains('Vyhledat', $html);
	assertHasSubmitSignalField($html, 'searchForm');
});

test('ExportPresenter exportForm renders with the submit signal field', function () {
	$presenter = new ExportPresenter(createGoogleServiceStub(), new CalendarSearchFacade(), new EmptyDayFiller(), new ExportFormFactory());
	$html = renderComponent('exportForm', $presenter);

	Assert::contains('Exportovat', $html);
	assertHasSubmitSignalField($html, 'exportForm');
});

test('EventPresenter updateEventForm renders with the submit signal field', function () {
	$presenter = new EventPresenter(createGoogleServiceStub());
	$html = renderComponent('updateEventForm', $presenter);

	Assert::contains('Uložit', $html);
	assertHasSubmitSignalField($html, 'updateEventForm');
});
