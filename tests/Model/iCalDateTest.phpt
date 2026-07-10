<?php declare(strict_types=1);

use Tester\Assert;
use App\Model\Calendar\iCalDate;

require __DIR__ . '/../bootstrap.php';

test('Render() formats the underlying timestamp', function () {
	$date = new iCalDate(strtotime('2024-03-15 10:30:00'));
	Assert::same('2024-03-15 10:30:00', $date->Render());
});

test('DaysInMonth() accounts for leap years', function () {
	$date = new iCalDate(strtotime('2024-02-01'));
	Assert::same(29, $date->DaysInMonth(2, 2024));
	Assert::same(28, $date->DaysInMonth(2, 2023));
	Assert::same(31, $date->DaysInMonth(1, 2024));
	Assert::same(30, $date->DaysInMonth(4, 2024));
});

test('GreaterThan()/LessThan() compare dates correctly', function () {
	$earlier = new iCalDate(strtotime('2024-01-01 00:00:00'));
	$later = new iCalDate(strtotime('2024-06-01 00:00:00'));
	Assert::true($later->GreaterThan($earlier));
	Assert::true($earlier->LessThan($later));
	Assert::false($earlier->GreaterThan($later));
});

test('AddDays() rolls over month/year boundaries', function () {
	$date = new iCalDate(strtotime('2024-12-30 12:00:00'));
	$date->AddDays(3);
	Assert::same('2025-01-02', $date->Render('Y-m-d'));
});
