<?php

namespace App\Model\Calendar;

/**
 * Orchestrates the search-form business rules shared by CalendarPresenter (viewing
 * events) and ExportPresenter (exporting the same search's events) - keeping this out
 * of the presenters themselves per Nette's own guidance that presenters shouldn't hold
 * business logic (https://doc.nette.org/en/application/presenters).
 */
class CalendarSearchFacade
{
	private const TAXES_DEFAULTS = [
		'social_employer' => 0.25,
		'health_employer' => 0.09,
		'social_employee' => 0.065,
		'health_employee' => 0.045,
		'student' => true,
		'children' => 0,
		'tax' => 0.15,
		'ztp' => false,
		'retirement_lvl' => 0,
	];

	private const TAX_PARAMS = [
		'personal' => 24840,
		'student' => 4020,
		'1_child' => 13404,
		'2_child' => 15804,
		'3_child' => 17004,
		'other_child' => 17004,
		'ztp' => 16140,
		'1_inv' => 2520,
		'2_inv' => 2520,
		'3_inv' => 5040,
	];

	/** @return array<string, bool|int|float> */
	public function getTaxesDefaults(): array
	{
		return self::TAXES_DEFAULTS;
	}

	/** @return array<string, int> */
	public function getTaxParams(): array
	{
		return self::TAX_PARAMS;
	}

	/**
	 * Groups an event set into per-day entries when the original search requested it -
	 * previously duplicated verbatim in both CalendarPresenter::perform() and
	 * ExportPresenter::perform(), since export re-applies the same original search's
	 * join_days/workmode choice to its own freshly-fetched event set.
	 */
	public function applyJoinDays(EventContainer $events, ?object $searchOptions): void
	{
		if ($searchOptions && $searchOptions->join_days) {
			$events->joinDays((bool) ($searchOptions->workmode ?? false));
		}
	}
}
