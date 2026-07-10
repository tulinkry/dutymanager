<?php declare(strict_types=1);

use App\Model\Calendar\iCalDate;
use App\Model\Calendar\EventContainer;
use App\Model\Google\GoogleCalendarService;

/**
 * A test double for App\Model\Google\GoogleCalendarService that never touches the real
 * Google API. Captures every getEvents() call so tests can assert exactly what filter
 * criteria a presenter passed through, without needing a live Google account.
 */
class FakeGoogleCalendarService extends GoogleCalendarService
{
	/** @var array<int, array{id: mixed, by: mixed}> */
	public array $getEventsCalls = [];

	public function __construct()
	{
		// Deliberately skip the parent constructor - no real Google\Service\Calendar needed.
	}

	public function getCalendar($id)
	{
		return (object) ['summary' => 'Fake Calendar', 'description' => ''];
	}

	public function getEvents($id, $by = null)
	{
		$this->getEventsCalls[] = ['id' => $id, 'by' => $by];

		$container = new EventContainer([]);
		$container->start = new iCalDate(strtotime('2026-01-01'));
		$container->end = new iCalDate(strtotime('2026-01-31'));

		return ['events' => $container, 'week_events' => clone $container];
	}
}
