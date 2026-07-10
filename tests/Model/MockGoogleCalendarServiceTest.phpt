<?php declare(strict_types=1);

use Tester\Assert;
use App\Model\Google\MockGoogleCalendarService;

require __DIR__ . '/../bootstrap.php';

// Mirrors every field CalendarPresenter's real search form always submits (see
// CalendarPresenter::createComponentSearchForm()) - a partial stdClass here would
// hit "Undefined property" warnings deep in GoogleCalendarService::getEvents(),
// which a real submitted form never triggers since every field has a default.
function makeSearchOptions(string $from, string $to, array $overrides = []): object
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
		'taxes_container' => (object) [],
	], $overrides);
}

test('getCalendars() returns a fixed set of demo calendars', function () {
	$service = new MockGoogleCalendarService();
	$items = $service->getCalendars()->getItems();

	Assert::same(3, count($items));
	Assert::same(['demo-calendar-1', 'demo-calendar-2', 'demo-calendar-3'], array_map(fn($c) => $c->getId(), $items));
	Assert::same('Kavárna Nádraží', $items[0]->getSummary());
});

test('getCalendar() returns the matching demo calendar by id', function () {
	$service = new MockGoogleCalendarService();
	$calendar = $service->getCalendar('demo-calendar-2');

	Assert::same('demo-calendar-2', $calendar->getId());
	Assert::same('Bar Central', $calendar->getSummary());
});

test('getEvents() generates the same weekday events every time (not random)', function () {
	$service = new MockGoogleCalendarService();
	$by = makeSearchOptions('2026-01-05 00:00:00', '2026-01-09 23:59:59'); // Mon-Fri

	$first = $service->getEvents('demo-calendar-1', $by)['events'];
	$second = $service->getEvents('demo-calendar-1', $by)['events'];

	// One event per weekday, Mon-Fri, no weekend events.
	Assert::same(5, $first->count);
	Assert::equal(
		array_map(fn($e) => [$e->m_Start->Render('Y-m-d H:i'), $e->m_Summary], $first->events),
		array_map(fn($e) => [$e->m_Start->Render('Y-m-d H:i'), $e->m_Summary], $second->events)
	);
});

test('getEvents() only generates weekday shifts, never weekends', function () {
	$service = new MockGoogleCalendarService();
	$by = makeSearchOptions('2026-01-10 00:00:00', '2026-01-11 23:59:59'); // Sat-Sun

	$events = $service->getEvents('demo-calendar-1', $by)['events'];

	Assert::same(0, $events->count);
});

test('getEvent() reconstructs the same event getEvents() would show for that date', function () {
	$service = new MockGoogleCalendarService();
	$by = makeSearchOptions('2026-01-05 00:00:00', '2026-01-05 23:59:59');
	$listed = $service->getEvents('demo-calendar-1', $by)['events']->events;
	$listedEvent = reset($listed);

	$single = $service->getEvent('demo-calendar-1', '2026-01-05');

	Assert::same($listedEvent->m_Summary, $single->getSummary());
	Assert::same('2026-01-05T09:00:00', $single->getStart()->getDateTime());
});
