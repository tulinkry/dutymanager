<?php

namespace Tulinkry\Components\Export;

use Nette\Object;
use Nette\Latte;
use Tulinkry\Google\EventContainer;

class Xml extends Exporter {

	protected $id = "xml";
	protected $name = "xml";

	public function updateTemplate ( $template ) {
		parent::updateTemplate($template);

		$template->tags = array(
	        1 => [ "tag" => "date", "attributes" => [ "format" => $this->options->basic->date_format, "type" => "date" ]],
	        2 => [ "tag" => "day", "attributes" => [ "type" => "string"]],
	        3 => [ "tag" => "duration", "attributes" => [ "type" => "double"]],
	        4 => [ "tag" => "name", "attributes" => [ "type" => "string"]],
	        5 => [ "tag" => "begin", "attributes" => [ "format" => $this->options->basic->block_format, "type" => "date" ]],
	        6 => [ "tag" => "end", "attributes" => [ "format" => $this->options->basic->block_format, "type" => "date" ]],
	        7 => [ "tag" => "price", "attributes" => [ "type" => "double"]],
	    );

        if($this->options->basic->breaks) {
            $format = $this->options->basic->break_date_format;
            $template->listBreaks = true;
            $template->breaks = array (
               0 => [ "tag" => "begin", 
                      "attributes" => [ "format" => $this->options->basic->break_date_format, "type" => "date" ]],
               1 => [ "tag" => "end", 
                      "attributes" => [ "format" => $this->options->basic->break_date_format, "type" => "date" ]],
            );
            $template->breakHandlers = array (
               0 => function ($date) use ($format) { return $date->Render($format); },
               1 => function ($date) use ($format) { return $date->Render($format); },
            );
        }
    }
}

