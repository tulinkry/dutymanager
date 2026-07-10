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

function createStartedExportPresenter(): ExportPresenter
{
    $fake = new FakeGoogleCalendarService();
    $presenter = new ExportPresenter($fake, new CalendarSearchFacade(), new EmptyDayFiller(), new ExportFormFactory());
    injectFakePresenterDependencies($presenter);

    $presenter->getSession()->start();
    $search = $presenter->getSession('search');
    $search['calendar_id'] = 'some-calendar-id';
    $search['form_data'] = (object) ['from_time' => '2026-01-01 00:00:00', 'to_time' => '2026-01-31 23:59:59'];

    $presenter->startup();

    return $presenter;
}

// Regression test for a real bug: clicking "Pridat sloupec" (Add column) on the
// Export page 500'd with "Call to undefined method ExportPresenter::invalidateControl()"
// (renamed to redrawControl() in current Nette), and then, once that was fixed,
// with "Cannot read an undeclared property Tulinkry\Application\UI\Form::$presenter"
// and "...Nette\Forms\Container::$parent" - Nette 2.x-era magic properties
// ($button->form->presenter, $button->parent, $container->parent/->name) removed
// from current Nette, requiring getForm()/getPresenter()/getParent()/getName().
// The second set of fixes lives in vendor/kdyby/forms-replicator itself, applied
// via patches/kdyby-replicator-monitor-callback.patch (an unreported upstream bug).
//
// The only way to catch these is to actually invoke the button's real onClick
// closure (the same one Nette\Forms\Form::fireEvents() calls when a submit button
// is clicked) - calling $replicator->createOne()/remove() directly bypasses the
// buggy code entirely and would pass even with the bug present.
test('clicking "add" invokes the real onClick closure and creates a row', function () {
    $presenter = createStartedExportPresenter();
    $form = $presenter->getComponent('exportForm');
    $fields = $form['fields'];

    Assert::same(0, iterator_count($fields->getContainers()));

    $addButton = $fields['add'];
    foreach ($addButton->onClick as $callback) {
        $callback($addButton);
    }

    Assert::same(1, iterator_count($fields->getContainers()));
});

test('clicking "remove" invokes the real onClick closure and removes the row', function () {
    $presenter = createStartedExportPresenter();
    $form = $presenter->getComponent('exportForm');
    $fields = $form['fields'];

    $addButton = $fields['add'];
    foreach ($addButton->onClick as $callback) {
        $callback($addButton);
    }
    Assert::same(1, iterator_count($fields->getContainers()));

    $row = iterator_to_array($fields->getContainers())[0];
    $removeButton = $row['remove'];
    foreach ($removeButton->onClick as $callback) {
        $callback($removeButton);
    }

    Assert::same(0, iterator_count($fields->getContainers()));
});
