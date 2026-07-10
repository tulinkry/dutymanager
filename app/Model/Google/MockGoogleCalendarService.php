<?php

namespace App\Model\Google;

use App\Model\Calendar\iCalDate;
use Google\Service\Calendar\Calendar;
use Google\Service\Calendar\CalendarList;
use Google\Service\Calendar\CalendarListEntry;
use Google\Service\Calendar\Event as GoogleEvent;
use Google\Service\Calendar\EventDateTime;

/**
 * Stands in for GoogleCalendarService when the MOCK_GOOGLE_API env var is set -
 * lets the app run end-to-end (calendar list, event browsing/export, event edit)
 * without any real Google account or OAuth credentials. Events are generated
 * deterministically from the requested date (not random), so the same day always
 * shows the same event - browsing a different month just generates that month's
 * events on the fly instead of reading from a fixed pre-stored dataset.
 *
 * Reuses GoogleCalendarService::applyFilters()/addEvent() so search filtering and
 * pricing behave identically to the real, Google-backed implementation.
 */
class MockGoogleCalendarService extends GoogleCalendarService
{
	/** @var array<int, array{summary: string, description: string, hours: array{int, int}[], titles: string[]}> */
	private const CALENDARS = [
		['id' => 'demo-calendar-1', 'summary' => 'Kavárna Nádraží', 'description' => 'Denní směny v kavárně', 'hours' => [9, 17], 'titles' => ['Ranní směna', 'Odpolední směna', 'Směna za kolegu']],
		['id' => 'demo-calendar-2', 'summary' => 'Bar Central', 'description' => 'Večerní směny v baru', 'hours' => [18, 23], 'titles' => ['Večerní směna', 'Barmanská směna', 'Akce na baru']],
		['id' => 'demo-calendar-3', 'summary' => 'Recepce Hotel', 'description' => 'Recepční služby', 'hours' => [7, 15], 'titles' => ['Ranní recepce', 'Denní recepce', 'Zástup na recepci']],
	];

	public function __construct()
	{
		// Deliberately skip the parent constructor - no real Google\Service\Calendar needed.
	}

	public function getCalendars()
	{
		$list = new CalendarList();
		$list->setItems(array_map(function (array $calendar) {
			$entry = new CalendarListEntry();
			$entry->setId($calendar['id']);
			$entry->setSummary($calendar['summary']);
			$entry->setDescription($calendar['description']);
			return $entry;
		}, self::CALENDARS));

		return $list;
	}

	public function getCalendar($id)
	{
		$calendar = new Calendar();
		$definition = $this->findCalendarDefinition($id);
		$calendar->setId($id);
		$calendar->setSummary($definition['summary']);
		$calendar->setDescription($definition['description']);

		return $calendar;
	}

	public function getEvent($calendarId, $eventId)
	{
		$event = $this->generateEventForDate($calendarId, new iCalDate(strtotime($eventId)));
		if (!$event) {
			throw new \RuntimeException("No mocked event on $eventId for calendar $calendarId.");
		}

		return $event;
	}

	public function updateEvent($calendarId, $eventId, \Google\Service\Calendar\Event $event)
	{
		// Mocked events are regenerated on demand from their date rather than stored,
		// so edits aren't persisted - the calling presenter only needs a success
		// response, it doesn't re-read the return value.
		return $event;
	}

	protected function fetchRawEvents($id, $from_bounded_time, $to_bounded_time): array
	{
		$events = [];
		$cursor = new iCalDate($from_bounded_time->_epoch);

		while ($cursor->_epoch <= $to_bounded_time->_epoch) {
			$event = $this->generateEventForDate($id, $cursor);
			if ($event) {
				$this->addEvent($event, $from_bounded_time, $to_bounded_time, $events);
			}
			$cursor->AddDays(1);
		}

		return $events;
	}

	private function generateEventForDate(string $calendarId, iCalDate $date): ?GoogleEvent
	{
		$dayOfWeek = (int) $date->Render('N'); // 1 (Monday) .. 7 (Sunday)
		if ($dayOfWeek > 5) {
			return null; // no weekend shifts in the demo data
		}

		$definition = $this->findCalendarDefinition($calendarId);
		$dayOfYear = (int) $date->Render('z');
		[$startHour, $endHour] = $definition['hours'];
		$summary = $definition['titles'][$dayOfYear % count($definition['titles'])];
		// Every third day has a fixed-price description, exercising the "only
		// description" / "combined" price_type filters in the search form.
		$hasDescription = $dayOfYear % 3 === 0;

		$day = $date->Render('Y-m-d');
		$event = new GoogleEvent();
		$event->setId($day);
		$event->setSummary($summary);
		$event->setDescription($hasDescription ? (string) (200 + ($dayOfYear % 5) * 50) : null);

		$start = new EventDateTime();
		$start->setDateTime($day . 'T' . sprintf('%02d:00:00', $startHour));
		$end = new EventDateTime();
		$end->setDateTime($day . 'T' . sprintf('%02d:00:00', $endHour));
		$event->setStart($start);
		$event->setEnd($end);

		return $event;
	}

	/** @return array{id: string, summary: string, description: string, hours: array{int, int}, titles: string[]} */
	private function findCalendarDefinition(string $id): array
	{
		foreach (self::CALENDARS as $calendar) {
			if ($calendar['id'] === $id) {
				return $calendar;
			}
		}

		// Unknown id (e.g. someone typed a random URL) - fall back to the first
		// demo calendar's shape rather than failing.
		return self::CALENDARS[0];
	}
}
