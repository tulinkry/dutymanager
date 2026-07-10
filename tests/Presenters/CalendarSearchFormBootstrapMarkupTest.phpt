<?php declare(strict_types=1);

use Tester\Assert;
use App\Model\Calendar\CalendarSearchFacade;
use App\Forms\SearchFormFactory;
use App\Presentation\Calendar\CalendarPresenter;
use App\Model\Google\MockGoogleCalendarService;

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../fixtures/presenter-test-helpers.php';

// Regression test for the Phase 6 renderer retirement: Bootstrap markup for the search
// form now lives entirely in viewCalendarEvents.latte's n:name-based @layout.latte
// "pair"/"checkboxPair" defines, not in a custom Form/renderer subclass - a mistake in
// that hand-written Latte (missing wrapper divs, a bad n:name tag) would only ever show
// up when the real template is compiled and rendered, which PresenterFormsRenderTest.phpt
// (component-only render) can no longer catch.
test('viewCalendarEvents renders the search form with Bootstrap markup', function () {
	$presenter = new CalendarPresenter(new MockGoogleCalendarService(), new CalendarSearchFacade(), new SearchFormFactory(new CalendarSearchFacade()));
	injectFakePresenterDependencies($presenter);
	$presenter->startup();
	$presenter->getSession()->start();

	$presenter->actionViewCalendarEvents('demo-calendar-1');
	$presenter->renderViewCalendarEvents('demo-calendar-1');

	$html = renderPresenterTemplate($presenter, __DIR__ . '/../../app/Presentation/Calendar/viewCalendarEvents.latte');

	Assert::contains('form-group clearfix', $html);
	Assert::contains('col-sm-3 control-label', $html);
	Assert::contains('col-sm-9', $html);
	Assert::contains('name="_do" value="searchForm-submit"', $html);
	// Checkbox rows must not repeat their caption text twice (once in the label column,
	// once again next to the checkbox) - see the Phase 6 plan's "clean them up" decision.
	Assert::same(1, substr_count($html, 'Odečíst pracovní pauzy'));
});
