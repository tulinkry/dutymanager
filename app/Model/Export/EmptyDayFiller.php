<?php

namespace App\Model\Export;

use App\Model\Calendar\Event;
use App\Model\Calendar\EventContainer;

/**
 * Fills date gaps in an already-fetched event range with empty placeholder days, so
 * "export empty days too" can show a row for every day in range, not just days with
 * an actual event - moved out of ExportPresenter since a domain algorithm like this
 * doesn't belong in a presenter (see https://doc.nette.org/en/application/presenters).
 */
class EmptyDayFiller
{
	public function fill(EventContainer $events): void
	{
		if (!count($events->events)) {
			return;
		}

		$first = array_slice($events->events, 0, 1, true);
		$first = $first[key($first)];
		$date = clone $events->start;
		$evs = clone $events;

		for ($i = $events->start->Render("j"); $i < $first->m_Start->Render("j"); $i++, $date->addDays(1)) {
			$events->events[] = new Event(clone $date, clone $date, true);
		}

		$prev = -1;
		foreach ($evs->events as $event) {
			for (; $i < $event->m_Start->Render("j"); $i++, $date->addDays(1)) {
				$events->events[] = new Event(clone $date, clone $date, true);
			}
			$i += $prev == $event->m_Start->Render("j") ? 0 : 1;
			$date->addDays($prev == $event->m_Start->Render("j") ? 0 : 1);
			$prev = $event->m_Start->Render("j");
		}

		for (; $i <= $events->end->Render("j"); $i++, $date->addDays(1)) {
			$events->events[] = new Event(clone $date, clone $date, true);
		}

		$events->applySort();
	}
}
