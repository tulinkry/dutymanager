<?php declare(strict_types=1);

use Tester\Assert;
use App\Model\Google\MockGoogleCalendarService;

require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../fixtures/presenter-test-helpers.php';

// Integration coverage for MockGoogleCalendarService::getEvents() across the search
// form's various lookup options (name match, price types, week rounding, taxes) -
// name_container/value_container/price_type mirror CalendarPresenter's real search
// form fields (see CalendarPresenter::createComponentSearchForm()), and taxes/
// taxes_container/tax_params mirror what CalendarPresenter::perform() actually
// submits, so these options are exercised exactly as production would submit them.

function lookupOptions(string $from, string $to, array $overrides = []): object
{
	return (object) array_merge([
		'from_time' => $from,
		'to_time' => $to,
		'name_container' => (object) ['text_match' => '', 'match_type' => 0],
		'value_container' => (object) ['price_type' => -1, 'price' => null],
		'workmode' => false,
		'week_summary' => false,
		'join_days' => false,
		'taxes' => false,
		'taxes_container' => [
			'social_employer' => 0.25, 'health_employer' => 0.09,
			'social_employee' => 0.065, 'health_employee' => 0.045,
			'student' => true, 'children' => 0, 'tax' => 0.15,
			'ztp' => false, 'retirement_lvl' => 0,
		],
		'tax_params' => [
			'personal' => 24840, 'student' => 4020, '1_child' => 13404,
			'2_child' => 15804, '3_child' => 17004, 'other_child' => 17004,
			'ztp' => 16140, '1_inv' => 2520, '2_inv' => 2520, '3_inv' => 5040,
		],
	], $overrides);
}

// A full working week (Mon-Fri) of "Kavárna Nádraží" shifts: 09:00-17:00 (8h),
// titles rotate through Ranní směna / Odpolední směna / Směna za kolegu, and
// every third day-of-year gets a fixed-price description (see
// MockGoogleCalendarService::generateEventForDate()) - this week (2026-07-06 to
// 2026-07-10) has some of each, which is what the price_type tests below rely on.
const WEEK_FROM = '2026-07-06 00:00:00'; // Monday
const WEEK_TO = '2026-07-10 23:59:59'; // Friday

test('no filters returns every weekday shift in range, unpriced', function () {
	$service = new MockGoogleCalendarService();
	$result = $service->getEvents('demo-calendar-1', lookupOptions(WEEK_FROM, WEEK_TO));

	Assert::same(5, $result['events']->count);
	foreach ($result['events']->events as $event) {
		Assert::same(0, $event->m_Price);
	}
});

test('name filter (regexp) keeps only summaries matching the pattern', function () {
	$service = new MockGoogleCalendarService();
	$by = lookupOptions(WEEK_FROM, WEEK_TO, [
		'name_container' => (object) ['text_match' => 'Ranní', 'match_type' => 0],
	]);

	$result = $service->getEvents('demo-calendar-1', $by);

	Assert::true($result['events']->count > 0);
	foreach ($result['events']->events as $event) {
		Assert::contains('Ranní', $event->m_Summary);
	}
});

test('name filter (exact match) excludes partial matches', function () {
	$service = new MockGoogleCalendarService();
	$by = lookupOptions(WEEK_FROM, WEEK_TO, [
		'name_container' => (object) ['text_match' => 'Ranní', 'match_type' => 1],
	]);

	// "Ranní" alone is never a full summary (always "Ranní směna") - exact match finds nothing.
	Assert::same(0, $service->getEvents('demo-calendar-1', $by)['events']->count);

	$byFull = lookupOptions(WEEK_FROM, WEEK_TO, [
		'name_container' => (object) ['text_match' => 'Ranní směna', 'match_type' => 1],
	]);
	foreach ($service->getEvents('demo-calendar-1', $byFull)['events']->events as $event) {
		Assert::same('Ranní směna', $event->m_Summary);
	}
});

test('price_type 0 (only description) drops undescribed events, prices from description', function () {
	$service = new MockGoogleCalendarService();
	$by = lookupOptions(WEEK_FROM, WEEK_TO, [
		'value_container' => (object) ['price_type' => 0, 'price' => null],
	]);

	$events = $service->getEvents('demo-calendar-1', $by)['events'];

	Assert::true($events->count > 0);
	foreach ($events->events as $event) {
		Assert::notSame(null, $event->m_Description);
		Assert::same((float) $event->m_Description, $event->m_Price);
	}
});

test('price_type 1 (flat rate) drops described events, prices by hours * rate', function () {
	$service = new MockGoogleCalendarService();
	$by = lookupOptions(WEEK_FROM, WEEK_TO, [
		'value_container' => (object) ['price_type' => 1, 'price' => 100],
	]);

	$events = $service->getEvents('demo-calendar-1', $by)['events'];

	Assert::true($events->count > 0);
	foreach ($events->events as $event) {
		// 8h shift * 100/h, no workmode deduction requested.
		Assert::same(800, $event->m_Price);
	}
});

test('price_type 1 with workmode deducts a break every 6 hours', function () {
	$service = new MockGoogleCalendarService();
	$by = lookupOptions(WEEK_FROM, WEEK_TO, [
		'value_container' => (object) ['price_type' => 1, 'price' => 100],
		'workmode' => true,
	]);

	$events = $service->getEvents('demo-calendar-1', $by)['events'];

	Assert::true($events->count > 0);
	foreach ($events->events as $event) {
		// 8h shift, floor(8/6)*0.5 = 0.5h deducted -> 7.5h * 100/h.
		Assert::same(750, $event->m_Price);
	}
});

test('price_type 2 (combined) prices described events from description, others computed', function () {
	$service = new MockGoogleCalendarService();
	$by = lookupOptions(WEEK_FROM, WEEK_TO, [
		'value_container' => (object) ['price_type' => 2, 'price' => 100],
	]);

	$events = $service->getEvents('demo-calendar-1', $by)['events'];

	Assert::same(5, $events->count); // nothing gets dropped under price_type 2
	foreach ($events->events as $event) {
		if ($event->m_Description !== null && $event->m_Description !== '') {
			Assert::same((float) $event->m_Description, $event->m_Price);
		} else {
			Assert::same(800, $event->m_Price);
		}
	}
});

test('price_type 3 (combined with adding) always computes, adding description if present', function () {
	$service = new MockGoogleCalendarService();
	$by = lookupOptions(WEEK_FROM, WEEK_TO, [
		'value_container' => (object) ['price_type' => 3, 'price' => 100],
	]);

	$events = $service->getEvents('demo-calendar-1', $by)['events'];

	Assert::same(5, $events->count);
	foreach ($events->events as $event) {
		// (int)800 + floatval(description) coerces back to float when a description
		// is present (real GoogleCalendarService::applyFilters() behavior) - compare
		// by value, not type.
		$expected = 800.0;
		if ($event->m_Description !== null && $event->m_Description !== '') {
			$expected += (float) $event->m_Description;
		}
		Assert::same($expected, (float) $event->m_Price);
	}
});

test('week_summary widens week_events to the whole week while events stays bounded', function () {
	$service = new MockGoogleCalendarService();
	// Wed-Thu only, but week_summary should pull the whole Mon-Fri week into week_events.
	$by = lookupOptions('2026-07-08 00:00:00', '2026-07-09 23:59:59', ['week_summary' => true]);

	$result = $service->getEvents('demo-calendar-1', $by);

	Assert::same(2, $result['events']->count); // filtered back down to Wed-Thu
	Assert::same(5, $result['week_events']->count); // full Mon-Fri week
});

test('taxes applies salary tax calculation to events and week_events', function () {
	$service = new MockGoogleCalendarService();
	$by = lookupOptions(WEEK_FROM, WEEK_TO, [
		'value_container' => (object) ['price_type' => 1, 'price' => 100],
		'taxes' => true,
	]);

	$result = $service->getEvents('demo-calendar-1', $by);

	Assert::true($result['events']->taxed);
	Assert::true($result['week_events']->taxed);
	// Taxing reduces gross price - every event's price must now be lower than the
	// untaxed 750 (workmode is false here, so 800 flat, minus tax deductions).
	foreach ($result['events']->events as $event) {
		Assert::true($event->m_Price < 800);
	}
});

test('join_days merges same-day adjacent events (presenter-level option)', function () {
	// join_days is applied by CalendarPresenter::perform() on the EventContainer
	// returned by getEvents(), not inside getEvents() itself - exercised directly
	// here on the same model class the presenter calls.
	$service = new MockGoogleCalendarService();
	$by = lookupOptions(WEEK_FROM, WEEK_TO);

	$events = $service->getEvents('demo-calendar-1', $by)['events'];
	$beforeCount = $events->count;
	$events->joinDays(false);

	// The mock only ever generates one shift per day, so join_days has nothing
	// adjacent to merge - this just proves it runs cleanly against mocked data.
	Assert::same($beforeCount, $events->count);
});
