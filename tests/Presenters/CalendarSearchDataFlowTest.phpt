<?php declare(strict_types=1);

use Tester\Assert;
use App\Model\Calendar\CalendarSearchFacade;
use App\Forms\SearchFormFactory;
use App\Presentation\Calendar\CalendarPresenter;

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../fixtures/FakeGoogleCalendarService.php';
require __DIR__ . '/../fixtures/presenter-test-helpers.php';

// Regression test for a real bug: submitting CalendarPresenter's search form silently
// did nothing (always showed the current month, ignored the date range/needle) because
// Tulinkry\Forms\Form::render() skipped Nette\Application\UI\Form::beforeRender(), which
// is what injects the hidden "_do" field a submission needs to be recognized at all -
// see libs/tulinkry/Forms/Form.php. This drives the presenter's own business logic
// (searchFormSucceeded -> session -> perform -> getEvents) directly, with a fake Google
// service standing in for the real API, so we can assert exactly what filter criteria
// would have been sent to Google - without a live account and without needing to
// simulate Nette's full HTTP/routing/signal dispatch just to prove the wiring works.

test('search form submission stores criteria in the session', function () {
	$fake = new FakeGoogleCalendarService();
	$presenter = new CalendarPresenter($fake, new CalendarSearchFacade(), new SearchFormFactory(new CalendarSearchFacade()));
	injectFakePresenterDependencies($presenter);
	$presenter->getSession()->start();

	$form = $presenter->getComponent('searchForm');
	$form->setValues([
		'name_container' => ['match_type' => 0, 'text_match' => 'Foo'],
		'value_container' => ['price_type' => 2, 'price' => 100],
		'from_time' => '2026-01-01 00:00:00',
		'to_time' => '2026-01-31 23:59:59',
	]);
	$form->setSubmittedBy($form['send']);

	$presenter->searchFormSucceeded($form);

	$search = $presenter->getSession('search');
	Assert::true(isset($search['form_data']), 'searchFormSucceeded should store the submitted criteria in the session');
	Assert::same('2026-01-01 00:00:00', $search['form_data']->from_time);
	Assert::same('2026-01-31 23:59:59', $search['form_data']->to_time);
	Assert::same('Foo', $search['form_data']->name_container->text_match);

	$presenter->renderViewCalendarEvents('some-calendar-id');

	Assert::same(1, count($fake->getEventsCalls), 'viewing the calendar after a search should query Google exactly once');
	$call = $fake->getEventsCalls[0];
	Assert::same('some-calendar-id', $call['id']);
	Assert::same('2026-01-01 00:00:00', $call['by']->from_time);
	Assert::same('2026-01-31 23:59:59', $call['by']->to_time);
});
