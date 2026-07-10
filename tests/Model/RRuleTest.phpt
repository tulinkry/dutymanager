<?php declare(strict_types=1);

use Tester\Assert;
use App\Model\Calendar\iCalDate;
use App\Model\Calendar\RRule;

require __DIR__ . '/../bootstrap.php';

// Regression test for the PHP4-style constructor (function RRule(...) instead of
// __construct) - on PHP 8 that method is just a plain method, never auto-invoked,
// so $this->_first/_dates/_current stay null and GetNext() fatals.
test('single-occurrence rule yields exactly one date then null', function () {
	$start = new iCalDate(strtotime('2024-01-01 09:00:00'));
	$rule = new RRule($start, 'FREQ=DAILY;COUNT=1');

	$first = $rule->GetNext();
	Assert::notSame(null, $first);
	Assert::same('2024-01-01 09:00:00', $first->Render());

	$second = $rule->GetNext();
	Assert::same(null, $second);
});

test('rule string with an "RRULE:" prefix is accepted', function () {
	$start = new iCalDate(strtotime('2024-01-01 09:00:00'));
	$rule = new RRule($start, 'RRULE:FREQ=DAILY;COUNT=1');

	$first = $rule->GetNext();
	Assert::notSame(null, $first);
	Assert::same('2024-01-01 09:00:00', $first->Render());
});
