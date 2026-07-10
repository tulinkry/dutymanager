<?php declare(strict_types=1);

use Tester\Assert;
use App\Model\Calendar\Event;
use App\Model\Calendar\iCalDate;
use App\Model\Calendar\EventContainer;
use App\Model\Export\Exporter;
use App\Model\Export\Csv;
use App\Model\Export\Xml;

require __DIR__ . '/../bootstrap.php';

// Full render coverage for the CSV/XML export templates - not just "updateTemplate()
// doesn't throw" (see ExportUpdateTemplateTest.phpt), but the actual rendered output,
// since bugs in csv.latte/xml.latte themselves (wrong column data, broken tags) would
// only show up once the template is compiled and executed.
function makeSampleEvents(): EventContainer
{
    $event1 = new Event(new iCalDate(strtotime('2026-01-05 09:00:00')), new iCalDate(strtotime('2026-01-05 12:00:00')));
    $event1->m_Summary = 'Ranní směna';
    $event1->m_Duration = 3;
    $event1->m_Price = 450;
    $event1->m_Breaks = [];

    $event2 = new Event(new iCalDate(strtotime('2026-01-06 13:00:00')), new iCalDate(strtotime('2026-01-06 17:00:00')));
    $event2->m_Summary = 'Odpolední směna';
    $event2->m_Duration = 4;
    $event2->m_Price = 600;
    $event2->m_Breaks = [];

    return new EventContainer([$event1, $event2]);
}

function makeExporter(string $class): Exporter
{
    $exporter = new $class();
    $exporter->setEvents(makeSampleEvents());
    $exporter->setCalendar((object) ['summary' => 'Test Calendar']);
    // Form::getValues() returns Nette\Utils\ArrayHash trees (both -> and [] access
    // work) - Exporter::updateTemplate()'s sort closure uses ['index'] array access,
    // so a plain stdClass here would not match production data shape.
    $exporter->setOptions(Nette\Utils\ArrayHash::from([
        'basic' => ['block_format' => 'H:i', 'date_format' => 'j. m. Y', 'day_render' => 'cz_0', 'breaks' => false],
        // type 1 = Datum, type 4 = Nazev (see Exporter::$columns)
        'fields' => [['type' => 1, 'index' => 1], ['type' => 4, 'index' => 2]],
        // type 3 = Delka (h) (see Exporter::$summaryColumns)
        'summary' => [['type' => 3, 'index' => 1]],
    ], true));
    return $exporter;
}

function renderExportTemplate(Exporter $exporter): string
{
    $latte = new Latte\Engine();
    $template = new Nette\Bridges\ApplicationLatte\DefaultTemplate($latte);
    $exporter->updateTemplate($template);
    return (string) $template;
}

test('csv.latte renders header, data rows and summary row', function () {
    $csv = renderExportTemplate(makeExporter(Csv::class));
    $lines = explode("\n", trim($csv));

    Assert::same('Datum,Název', $lines[0]);
    Assert::same('5. 01. 2026,Ranní směna', $lines[1]);
    Assert::same('6. 01. 2026,Odpolední směna', $lines[2]);
    Assert::same('Délka (h)', $lines[3]);
    Assert::same('7', $lines[4]);
});

test('xml.latte renders one <event> per event with the selected columns as tags', function () {
    $xml = renderExportTemplate(makeExporter(Xml::class));

    Assert::contains('<events>', $xml);
    Assert::contains('</events>', $xml);
    Assert::same(2, substr_count($xml, '<event>'));
    Assert::contains('<date format="j. m. Y" type="date">5. 01. 2026</date>', $xml);
    Assert::contains('<name type="string">Ranní směna</name>', $xml);
    Assert::contains('<date format="j. m. Y" type="date">6. 01. 2026</date>', $xml);
    Assert::contains('<name type="string">Odpolední směna</name>', $xml);
});
