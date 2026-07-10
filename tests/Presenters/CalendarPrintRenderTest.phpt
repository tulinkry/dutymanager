<?php declare(strict_types=1);

use Tester\Assert;
use App\Model\Calendar\CalendarSearchFacade;
use App\Forms\SearchFormFactory;
use App\Presentation\Calendar\CalendarPresenter;
use App\Model\Google\MockGoogleCalendarService;

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../fixtures/presenter-test-helpers.php';

// Regression test for a real bug reported from the browser: visiting
// /calendar/<id>/print/ 500'd. Renders the actual Calendar/printCalendarEvents.latte
// template (not just the presenter's own logic) since that's the only way to catch
// a template-level bug, using MockGoogleCalendarService for realistic event data.
test('printCalendarEvents renders with a prior search in session', function () {
	$presenter = new CalendarPresenter(new MockGoogleCalendarService(), new CalendarSearchFacade(), new SearchFormFactory(new CalendarSearchFacade()));
	injectFakePresenterDependencies($presenter);
	$presenter->startup();

	$presenter->getSession()->start();
	$presenter->getSession('search')['form_data'] = (object) [
		'from_time' => '2026-07-01 00:00:00',
		'to_time' => '2026-07-31 23:59:59',
		'name_container' => (object) ['text_match' => '', 'match_type' => 0],
		'value_container' => (object) ['price_type' => 1, 'price' => 150],
		'workmode' => false,
		'week_summary' => false,
		'join_days' => false,
		'taxes' => false,
		'taxes_container' => (object) [],
	];

	$presenter->renderPrintCalendarEvents('demo-calendar-1', 0);

	$html = renderPresenterTemplate($presenter, __DIR__ . '/../../app/Presentation/Calendar/printCalendarEvents.latte');

	Assert::contains('Kavárna Nádraží', $html);
	Assert::contains('Vyuctovani pro', $html);
});

test('printCalendarEvents renders with no prior search (fresh session)', function () {
	$presenter = new CalendarPresenter(new MockGoogleCalendarService(), new CalendarSearchFacade(), new SearchFormFactory(new CalendarSearchFacade()));
	injectFakePresenterDependencies($presenter);
	$presenter->startup();
	$presenter->getSession()->start();

	$presenter->renderPrintCalendarEvents('demo-calendar-1');

	$html = renderPresenterTemplate($presenter, __DIR__ . '/../../app/Presentation/Calendar/printCalendarEvents.latte');

	Assert::contains('Kavárna Nádraží', $html);
});
