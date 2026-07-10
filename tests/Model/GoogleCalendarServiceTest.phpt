<?php declare(strict_types=1);

use Tester\Assert;
use App\Model\Google\GoogleCalendarService;
use Google\Service\Calendar\Event as GoogleEvent;
use Google\Service\Calendar\EventDateTime;

require __DIR__ . '/../bootstrap.php';

function createGoogleCalendarService(): GoogleCalendarService
{
	return new GoogleCalendarService(new Google\Service\Calendar(new Google\Client()));
}

test('convertEventDates handles timed events (dateTime)', function () {
	$event = new GoogleEvent();
	$start = new EventDateTime();
	$start->setDateTime('2024-03-15T09:00:00');
	$event->setStart($start);
	$end = new EventDateTime();
	$end->setDateTime('2024-03-15T17:00:00');
	$event->setEnd($end);

	$method = new ReflectionMethod(GoogleCalendarService::class, 'convertEventDates');
	$method->setAccessible(true);
	[$startDate, $endDate] = $method->invoke(createGoogleCalendarService(), $event);

	Assert::same('2024-03-15 09:00:00', $startDate->Render());
	Assert::same('2024-03-15 17:00:00', $endDate->Render());
});

test('convertEventDates handles all-day events (date)', function () {
	$event = new GoogleEvent();
	$start = new EventDateTime();
	$start->setDate('2024-03-15');
	$event->setStart($start);
	$end = new EventDateTime();
	$end->setDate('2024-03-16');
	$event->setEnd($end);

	$method = new ReflectionMethod(GoogleCalendarService::class, 'convertEventDates');
	$method->setAccessible(true);
	[$startDate, $endDate] = $method->invoke(createGoogleCalendarService(), $event);

	Assert::same('2024-03-15 00:00:00', $startDate->Render());
	Assert::same('2024-03-16 00:00:00', $endDate->Render());
});
