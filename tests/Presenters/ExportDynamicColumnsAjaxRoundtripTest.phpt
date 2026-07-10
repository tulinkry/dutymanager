<?php declare(strict_types=1);

use Tester\Assert;
use App\Model\Calendar\CalendarSearchFacade;
use App\Model\Export\EmptyDayFiller;
use App\Forms\ExportFormFactory;
use App\Presentation\Export\ExportPresenter;

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../fixtures/FakeGoogleCalendarService.php';
require __DIR__ . '/../fixtures/presenter-test-helpers.php';

Kdyby\Replicator\Container::register();

// Regression test for a real bug reported from the browser: clicking "Pridat sloupec"
// (Add column) under "Sloupce" got stuck at 1 row no matter how many times it was
// clicked, and clicking "Pridat sloupec" under "Resume" appeared to move the box
// from "Sloupce" to "Resume" instead of adding a new one there.
//
// Root cause: kdyby/forms-replicator's Container relies on a legacy Nette 2.x API -
// monitor($type) used to call the object's own attached()/detached() methods when the
// monitored ancestor appeared. Current Nette's monitor() only invokes the explicit
// callback passed to it; it never calls attached() at all. Our earlier patch (to fix
// a "monitor() requires a callback" TypeError) passed an empty no-op callback, which
// made the code compile but silently disabled Container::loadHttpData()/createDefault()
// forever - the mechanism that reconstructs existing rows from a resubmitted AJAX POST
// body. Every replicator started from zero rows on every request; only the row created
// by that request's own "add" button click ever appeared, which looks exactly like
// "stuck at 1" (fields) or "moved to the other section" (fields lost its row while
// summary gained the one from its own click).
//
// This can only be caught by driving a REAL $presenter->run() with a realistic POST
// body across successive simulated AJAX requests (mirroring what a browser actually
// resubmits each time) - calling createOne()/onClick directly, or inspecting a single
// in-memory request, never exercises the broken reconstruction path at all.
function runFakeAjaxRequest(array $post): ExportPresenter
{
    $fake = new FakeGoogleCalendarService();
    $presenter = new ExportPresenter($fake, new CalendarSearchFacade(), new EmptyDayFiller(), new ExportFormFactory());

    $httpRequest = new Nette\Http\Request(
        new Nette\Http\UrlScript('http://localhost/export/'),
        post: $post,
        files: [],
        cookies: [],
        // sec-fetch-site is required or Nette\Application\UI\Form::signalReceived()
        // treats the request as cross-origin and silently redirects instead of firing
        // onSuccess/onClick - see Presenter::detectedCsrf().
        headers: ['x-requested-with' => 'XMLHttpRequest', 'sec-fetch-site' => 'same-origin'],
        method: 'POST',
    );
    $httpResponse = new Nette\Http\Response();
    $session = new Nette\Http\Session($httpRequest, $httpResponse);
    $session->setExpiration('30 days');
    $user = new Nette\Security\User(new FakeUserStorage());
    $router = (new App\Core\RouterFactory())->createRouter();
    $presenterFactory = new Nette\Application\PresenterFactory();
    $presenterFactory->setMapping(['*' => 'App\Presentation\*\**Presenter']);
    $presenter->injectPrimary($httpRequest, $httpResponse, $presenterFactory, $router, $session, $user, new FakeTemplateFactory());

    $presenter->getSession()->start();
    $search = $presenter->getSession('search');
    $search['calendar_id'] = 'some-calendar-id';
    $search['form_data'] = (object) ['from_time' => '2026-01-01 00:00:00', 'to_time' => '2026-01-31 23:59:59'];

    $request = new Nette\Application\Request('Export', 'POST', ['action' => 'default'], $post);
    $presenter->run($request);

    return $presenter;
}

function basicGroupPost(): array
{
    return ['format' => 'csv', 'date_format' => 'j. m. Y H:i', 'block_format' => 'H:i', 'break_date_format' => 'H:i', 'day_render' => 'cz_0'];
}

test('repeated "add" clicks keep growing the same replicator', function () {
    $presenter = runFakeAjaxRequest([
        '_do' => 'exportForm-submit',
        'basic' => basicGroupPost(),
        'fields' => ['add' => 'Přidat sloupec'],
    ]);
    $rows = iterator_to_array($presenter->getComponent('exportForm')['fields']->getContainers());
    Assert::same([0], array_keys($rows));

    // A real browser resubmits the row created by the previous response.
    $presenter = runFakeAjaxRequest([
        '_do' => 'exportForm-submit',
        'basic' => basicGroupPost(),
        'fields' => ['0' => ['type' => '1', 'index' => '1'], 'add' => 'Přidat sloupec'],
    ]);
    $rows = iterator_to_array($presenter->getComponent('exportForm')['fields']->getContainers());
    Assert::same([0, 1], array_keys($rows));
});

test('"add" under Resume does not remove the existing row under Sloupce', function () {
    $presenter = runFakeAjaxRequest([
        '_do' => 'exportForm-submit',
        'basic' => basicGroupPost(),
        'fields' => ['0' => ['type' => '1', 'index' => '1']],
        'summary' => ['add' => 'Přidat sloupec'],
    ]);
    $form = $presenter->getComponent('exportForm');

    Assert::same([0], array_keys(iterator_to_array($form['fields']->getContainers())));
    Assert::same([0], array_keys(iterator_to_array($form['summary']->getContainers())));
});
