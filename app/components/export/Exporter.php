<?php

namespace Tulinkry\Components\Export;

use Nette\Object;
use Nette\Latte;
use Nette\InvalidStateException;
use Nette\Reflection\ClassType;
use Tulinkry\Google\EventContainer;

abstract class Exporter extends Object {

	protected $templateName = "";
	protected $name = "";

    protected static $columns = array(
    			1 => "Datum",
                2 => "Den",
                3 => "Délka (h)",
                4 => "Název",
                5 => "Začátek",
                6 => "Konec",
                7 => "Cena",
                8 => "Přestávka",
    );

    protected static $summaryColumns = array(
        1 => "Průměrná délka (h)",
        2 => "Průměrná cena",
        3 => "Délka (h)",
        4 => "Cena",
        5 => "Počet událostí",
    );

    protected $events = null;
    protected $options = null;
    protected $calendar = null;

	public function __construct () {
		$reflection = new ClassType($this);
		$this->templateName = strtolower($reflection->getShortName()) .".latte";
	}

    public function setEvents($events) {
        $this->events = $events;
        return $this;
    }

    public function setCalendar($calendar) {
        $this->calendar = $calendar;
        return $this;
    }

    public function setOptions($options) {
        $this->options = $options;
        return $this;
    }

	public function updateTemplate ( $template ) {
		if ( ! $this->options || ! $this->events )
			throw new InvalidStateException ( "No options or events given for updating the template" );

		$options = $this->options;
		$events = $this->events;
        $template->setFile(__DIR__ . DIRECTORY_SEPARATOR . $this->templateName );

        $template->listBreaks = false; 
        $template->events = $this->events;
        $template->calendar = $this->calendar;
        $template->values = $options;

        $closure = function ( $a, $b ) {
            return ($a['index']) - ($b['index']);
        };

        $fields = (array)$template->values->fields;
        uasort($fields, $closure);
        $template->values->fields = $fields;

        $summary = (array)$template->values->summary;
        uasort($summary, $closure);
        $template->values->summary = $summary;

        $template->control = $this;
        $blockFormat = $options->basic->block_format;
        $dateFormat = $options->basic->date_format;
        $weekday = $options->basic->day_render;
        $lang = explode("_", $weekday) [0];
        $fmt = explode("_", $weekday) [1];

        $handlers = array (
                1 => function($event) use ($dateFormat) { return $event->m_Start->Render($dateFormat); },
                2 => function($event) use ($lang, $fmt) { return $event->m_Start->Weekday($fmt, $lang); },
                3 => function($event) { return $event->m_Duration; },
                4 => function($event) { return $event->m_Summary; },
                5 => function($event) use ($blockFormat) { 
                    if($event->isDummy()) return "";
                    return $event->m_Start->Render($blockFormat); 
                },
                6 => function($event) use ($blockFormat) { 
                    if($event->isDummy()) return "";
                    return $event->m_End->Render($blockFormat); 
                },
                7 => function($event) { return $event->m_Price; },
                8 => function($event) { return implode ( ", ", array_map ( function($e) { 
                       return count($e) >= 2 ? $e[0]->Render("H:i") . " - " . $e[1]->Render("H:i") : ""; 
                   }, $event -> m_Breaks ) ); 
                },
            );

        $summary = array (
            1 => function($events) { return $events->count > 0 ? number_format($events->duration / $events->count, 2, ".", "") : 0; },
            2 => function($events) { return $events->count > 0 ? number_format($events->price / $events->count, 2, ".", "") : 0; },
            3 => function($events) { return $events->duration; },
            4 => function($events) { return $events->price; },
            5 => function($events) { return $events->count; },
        );

        $template->handlers = $handlers;
        $template->summary = $summary;

        $template->columns = self::$columns;
        $template->summaryColumns = self::$summaryColumns;

        $template->events = $events;
        $template->calendar = 1;

    }
	
	public function getName () {
		return $this->name;
	}


    /**
     * Gets the value of columns.
     *
     * @return mixed
     */
    public static function getColumns()
    {
        return self::$columns;
    }


    /**
     * Gets the value of summaryColumns.
     *
     * @return mixed
     */
    public static function getSummaryColumns()
    {
        return self::$summaryColumns;
    }
}