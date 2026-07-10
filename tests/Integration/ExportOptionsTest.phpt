<?php declare(strict_types=1);

use Tester\Assert;
use App\Model\Calendar\CalendarSearchFacade;
use App\Model\Export\EmptyDayFiller;
use App\Forms\ExportFormFactory;
use App\Presentation\Export\ExportPresenter;
use App\Model\Google\MockGoogleCalendarService;

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../fixtures/presenter-test-helpers.php';

Kdyby\Replicator\Container::register();

// Integration coverage for ExportPresenter across its various export options
// (CSV/XML, column selection & order, empty_days, breaks) against
// MockGoogleCalendarService - drives the presenter the same way a real request
// would: session's "form_data" (from a prior search) + session's "export.formOptions"
// (from a prior export form submission), then renderExport() + the real template.

function makeExportPresenter(array $searchOverrides = []): ExportPresenter
{
	$presenter = new ExportPresenter(new MockGoogleCalendarService(), new CalendarSearchFacade(), new EmptyDayFiller(), new ExportFormFactory());
	injectFakePresenterDependencies($presenter);
	$presenter->getSession()->start();

	$search = $presenter->getSession('search');
	$search['calendar_id'] = 'demo-calendar-1';
	$search['form_data'] = (object) array_merge([
		'from_time' => '2026-07-06 00:00:00', // Monday
		'to_time' => '2026-07-12 23:59:59', // Sunday (includes the weekend gap)
		'name_container' => (object) ['text_match' => '', 'match_type' => 0],
		// price_type -1 matches no switch case in applyFilters(), so no events get
		// filtered/priced by the ORIGINAL search - these tests are about export
		// column selection/options, not lookup filtering (see CalendarLookupTest).
		'value_container' => (object) ['price_type' => -1, 'price' => null],
		'workmode' => false,
		'week_summary' => false,
		'join_days' => false,
		'taxes' => false,
		'taxes_container' => [],
	], $searchOverrides);

	return $presenter;
}

function setExportOptions(ExportPresenter $presenter, array $basic, array $fields, array $summary): void
{
	$presenter->getSession('export')->formOptions = Nette\Utils\ArrayHash::from([
		'basic' => array_merge(['format' => 'csv', 'date_format' => 'j. m. Y', 'block_format' => 'H:i', 'break_date_format' => 'H:i', 'day_render' => 'cz_0', 'empty_days' => false, 'breaks' => false], $basic),
		'fields' => $fields,
		'summary' => $summary,
	], true);
	$presenter->startup();
}

function renderedOutput(ExportPresenter $presenter): string
{
	$presenter->renderExport();
	return (string) $presenter->getTemplate();
}

/** ExportPresenter::$events is private - renderExport() populates it via perform(). */
function getExportedEvents(ExportPresenter $presenter): array
{
	$property = new ReflectionProperty(ExportPresenter::class, 'events');
	$property->setAccessible(true);
	return $property->getValue($presenter)->events;
}

test('CSV export respects the selected columns and their order', function () {
	$presenter = makeExportPresenter();
	setExportOptions(
		$presenter,
		['format' => 'csv'],
		[['type' => 4, 'index' => 1], ['type' => 1, 'index' => 2]], // Nazev first, then Datum
		[['type' => 5, 'index' => 1]] // Pocet udalosti
	);

	$lines = explode("\n", trim(renderedOutput($presenter)));

	Assert::same('Název,Datum', $lines[0]);
	// 5 weekday shifts in a Mon-Sun range, no empty_days.
	Assert::same(5, count($lines) - 1 - 2); // total lines minus header minus summary header+value
});

test('XML export respects the selected columns as tags', function () {
	$presenter = makeExportPresenter();
	setExportOptions(
		$presenter,
		['format' => 'xml'],
		[['type' => 1, 'index' => 1], ['type' => 7, 'index' => 2]], // Datum, Cena
		[['type' => 3, 'index' => 1]] // Delka (h)
	);

	$xml = renderedOutput($presenter);

	Assert::same(5, substr_count($xml, '<event>'));
	Assert::contains('<date', $xml);
	Assert::contains('<price', $xml);
	Assert::notContains('<name', $xml); // Nazev wasn't selected
});

test('empty_days fills weekend gaps with dummy rows', function () {
	$withoutEmptyDays = makeExportPresenter();
	setExportOptions($withoutEmptyDays, ['format' => 'csv', 'empty_days' => false], [['type' => 1, 'index' => 1]], [['type' => 5, 'index' => 1]]);
	$withoutEmptyDays->renderExport();
	Assert::same(5, count(getExportedEvents($withoutEmptyDays)));

	$withEmptyDays = makeExportPresenter();
	setExportOptions($withEmptyDays, ['format' => 'csv', 'empty_days' => true], [['type' => 1, 'index' => 1]], [['type' => 5, 'index' => 1]]);
	$withEmptyDays->renderExport();
	$events = getExportedEvents($withEmptyDays);

	// Mon-Sun = 7 days, only 5 have real shifts - empty_days must add the 2 weekend gaps.
	Assert::same(7, count($events));
	Assert::same(2, count(array_filter($events, fn($e) => $e->isDummy())));
});

test('breaks are exported when join_days+workmode split a long shift and "breaks" is enabled', function () {
	// join_days/workmode come from the ORIGINAL search (form_data), applied by
	// ExportPresenter::perform() itself - "breaks" is a separate export-form option
	// controlling whether Xml.php actually renders the resulting break tags.
	$presenter = makeExportPresenter(['join_days' => true, 'workmode' => true]);
	setExportOptions(
		$presenter,
		['format' => 'xml', 'breaks' => true],
		[['type' => 1, 'index' => 1]],
		[['type' => 3, 'index' => 1]]
	);

	$xml = renderedOutput($presenter);

	// Cafe shifts are 8h (09:00-17:00) - joinDays(workmode: true) splits any shift
	// over 6h into a break, so every one of the 5 weekday events gets exactly one.
	Assert::same(5, substr_count($xml, '<breaks>'));
	Assert::same(5, substr_count($xml, '<break>'));
});

test('breaks are not exported when the "breaks" option is off, even if events have them', function () {
	$presenter = makeExportPresenter(['join_days' => true, 'workmode' => true]);
	setExportOptions(
		$presenter,
		['format' => 'xml', 'breaks' => false],
		[['type' => 1, 'index' => 1]],
		[['type' => 3, 'index' => 1]]
	);

	$xml = renderedOutput($presenter);

	Assert::notContains('<breaks>', $xml);
});
