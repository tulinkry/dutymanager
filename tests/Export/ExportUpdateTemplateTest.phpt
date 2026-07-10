<?php declare(strict_types=1);

use Tester\Assert;
use App\Model\Calendar\EventContainer;
use App\Model\Export\Csv;

require __DIR__ . '/../bootstrap.php';

// Regression test for a real bug: performing an export (after searching + adding
// export columns) 500'd with "TypeError: Cannot assign App\Model\Export\Csv
// to property Nette\Bridges\ApplicationLatte\DefaultTemplate::$control of type
// Nette\Application\UI\Control". Exporter::updateTemplate() did `$template->control =
// $this;` (Nette 2.x templates had no typed properties, so any value could be stashed
// there) - current Nette's DefaultTemplate declares $control as a strictly-typed
// ?Control property, and $this here is the Exporter (Csv/Xml), not a Control. Grepping
// both csv.latte and xml.latte confirms $control is never read anywhere - it was dead
// code even before the type became enforced, so the fix is to just remove the line.
test('updateTemplate() populates the template without throwing', function () {
    $latte = new Latte\Engine();
    $template = new Nette\Bridges\ApplicationLatte\DefaultTemplate($latte);

    $exporter = new Csv();
    $exporter->setEvents(new EventContainer([]));
    $exporter->setCalendar((object) ['summary' => 'Test Calendar']);
    $exporter->setOptions((object) [
        'basic' => (object) ['block_format' => 'H:i', 'date_format' => 'j. m. Y H:i', 'day_render' => 'cz_0'],
        'fields' => [(object) ['type' => 1, 'index' => 1]],
        'summary' => [(object) ['type' => 1, 'index' => 1]],
    ]);

    $exporter->updateTemplate($template);

    Assert::same(8, count($template->columns));
    Assert::same(1, count($template->values->fields));
});
