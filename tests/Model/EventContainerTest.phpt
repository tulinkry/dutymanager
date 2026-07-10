<?php declare(strict_types=1);

use Tester\Assert;
use App\Model\Calendar\Event;
use App\Model\Calendar\EventContainer;

require __DIR__ . '/../bootstrap.php';

test('aggregates price/duration/count/hour_tax across events', function () {
	$e1 = new Event('2024-01-01 08:00:00', '2024-01-01 12:00:00');
	$e1->m_Price = 100;
	$e1->m_Duration = 4;

	$e2 = new Event('2024-01-08 08:00:00', '2024-01-08 12:00:00');
	$e2->m_Price = 200;
	$e2->m_Duration = 4;

	$container = new EventContainer([$e1, $e2]);

	Assert::same(300, $container->price);
	Assert::same(8, $container->duration);
	Assert::same(2, $container->count);
	Assert::same(37.5, $container->hour_tax);
});

test('applySort orders events chronologically regardless of input key order', function () {
	$early = new Event('2024-01-01 08:00:00', '2024-01-01 12:00:00');
	$early->m_Price = 0;
	$early->m_Duration = 0;

	$late = new Event('2024-06-01 08:00:00', '2024-06-01 12:00:00');
	$late->m_Price = 0;
	$late->m_Duration = 0;

	$container = new EventContainer(['b' => $late, 'a' => $early]);
	$container->applySort();

	$order = array_map(
		fn($event) => $event->m_Start->Render('Y-m-d'),
		array_values($container->events)
	);
	Assert::same(['2024-01-01', '2024-06-01'], $order);
});
