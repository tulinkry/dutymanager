<?php


use Tulinkry\Application\UI;
use Tulinkry\Application\UI\Form;
use Nette\ArrayHash;
use Nette\Application\Responses\TextResponse;
use Tulinkry\Components\Export;

class ExportPresenter extends BasePresenter
{
    /** @inject @var Tulinkry\Google\GoogleCalendarService */
    public $googleService;

    private $calendarSearchOptions = NULL;
    private $exportFormOptions = NULL;
    private $calendarId = 0;
    private $events = null;
    private $calendar = null;

    private $supportedFormats = array ();


    public function startup()
    {
        parent::startup();

        $this->supportedFormats = array (
            "csv" => new Export\Csv,
            "xml" => new Export\Xml,
        );

        if(!isset($_SESSION["calendar_id"]) || !$_SESSION["calendar_id"]) {
            $this->flashMessage("Došlo k chybě, patrně vám vypršelo aktuální sezení. Pokračujte výběrem kalendáře.", "danger" );
            $this->redirect("Index:default");
        }

        if(!isset($_SESSION["form_data"]) || !$_SESSION["form_data"]) {
            $this->flashMessage("Došlo k chybě, zřejmě jste nevybrali žádné události ve vyhledávání. Pokračujte hledáním událostí.", "danger" );
            $this->redirect("Calendar:viewCalendarEvents", [ "id" => $_SESSION["calendar_id"] ] );
        }

        $this->calendarSearchOptions = $_SESSION["form_data"];
        $this->calendarId = $_SESSION["calendar_id"];
        $this->template->calendarId = $this->calendarId;

        if($this->getSession()->hasSection("export") && isset($this->getSession("export")->formOptions)) {
            $this->exportFormOptions = $this->getSession("export")->formOptions;
        }
    }


    protected function createComponentExportForm()
    {
        $form = new UI\Form;
        $form -> addGroup ("Formát");

        $basic = $form -> addContainer ("basic");

        $basic -> addSelect ( "format", "Formát", array_map(function($a) { return $a->name; }, $this->supportedFormats) )
              -> setPrompt ( "Zvolte výstupní formát" )
              -> setRequired ( "Zvolte výstupní formát" );

        $basic -> addText ( "date_format", "Formát data (sloupec datum)" )
              -> setAttribute ( "placeholder", "j. m. Y H:i// @see date()" )
              -> setDefaultValue ( "j. m. Y H:i" )
              -> addRule ( $form::FILLED, "Formát data (sloupec datum) nesmí být prázdný" );

        $basic -> addText ( "block_format", "Formát data (sloupec začátek a konec)" )
              -> setAttribute ( "placeholder", "H:i// @see date()" )
              -> setDefaultValue ( "H:i" )
              -> addRule ( $form::FILLED, "Formát data (sloupec začátek a konec) nesmí být prázdný" );

        $basic -> addCheckbox ( "empty_days", "Zobrazit i prázdné dny" );
        $basic -> addCheckbox ( "breaks", "Zobrazit přestávky" );
        $basic -> addText ( "break_date_format", "Formát přestávek" )
              -> setAttribute ( "placeholder", "H:i // @see date()" )
              -> setDefaultValue ( "H:i" )
              -> addRule ( $form::FILLED, "Formát přestávek nesmí být prázdný" );

        $basic -> addSelect ( "day_render", "Formát dne", array (
                "Česky" => array ( "cz_1" => "Krátký (Pá)", "cz_0" => "Dlouhý (Pátek)" ),
                "Anglicky" => array ( "en_2" => "Krátký (Fr)", "en_1" => "Delší (Fri)", "en_0" => "Dlouhý (Friday)" ),
            ) )
               -> setRequired ( "Formát dne nesmí být prázdný" )
               -> setDefaultValue ( "cz_0" )
               -> setPrompt ( "Zvolte formát výpisu dne" );

        $columns = Export\Exporter::getColumns();
        $form->addGroup("Sloupce");
        $replicator = $form -> addDynamic ( "fields", function ($container) use ($columns) {
            $container->addSelect("type", "Sloupec", $columns);
            $container->addSelect("index", "Pořadí sloupce", Tulinkry\Utils\Arrays::createSequence(1, count($columns), true) )
                      ->setAttribute("class", "column_index")
                      ->setDefaultValue($container->name + 1);
            $container->addSubmit('remove', 'Odstranit')
                ->setValidationScope(FALSE)
                ->setAttribute( "class", "btn btn-danger ajax")
                ->onClick[] = function($button) {
                    $button->form->presenter->invalidateControl("exportForm");
                    if($button->form->presenter->exportFormOptions &&
                        isset($button->form->presenter->exportFormOptions->fields[$button->parent->name])) {
                        unset($button->form->presenter->exportFormOptions->fields[$button->parent->name]);
                    }
                    $button->parent->parent->remove($button->parent, TRUE);
                };

        }, 0 );

        $replicator->addSubmit("add", "Přidat sloupec")
                   ->setAttribute( "class", "btn btn-success ajax")
                   ->setValidationScope(FALSE)
                   ->onClick[] = function($button) {
                        $button->form->presenter->invalidateControl("exportForm");
                        $button->parent->createOne();
                   };

        $form->addGroup("Resumé");
        $columns = Export\Exporter::getSummaryColumns();
        $replicator = $form->addDynamic("summary", function($container) use ($columns) {
            $container->addSelect("type", "Sloupec", $columns);
            $container->addSelect("index", "Pořadí sloupce", Tulinkry\Utils\Arrays::createSequence(1, count($columns), true) )
                      ->setAttribute("class", "column_index")
                      ->setDefaultValue($container->name + 1);
            $container->addSubmit('remove', 'Odstranit')
                ->setValidationScope(FALSE)
                ->setAttribute( "class", "btn btn-danger ajax")
                ->onClick[] = function($button) {
                    $button->form->presenter->invalidateControl("exportForm");
                    if($button->form->presenter->exportFormOptions &&
                        isset($button->form->presenter->exportFormOptions->summary[$button->parent->name])) {
                        unset($button->form->presenter->exportFormOptions->summary[$button->parent->name]);
                    }
                    $button->parent->parent->remove($button->parent, TRUE);
                };

        }, 0 );

        $replicator->addSubmit("add", "Přidat sloupec")
                   ->setAttribute( "class", "btn btn-success ajax")
                   ->setValidationScope(FALSE)
                   ->onClick[] = function($button) {
                        $button->form->presenter->invalidateControl("exportForm");
                        $button->parent->createOne();
                   };


        $form -> addGroup ();
        $form->addButton('help', 'Help')
             ->setAttribute ( "id", "help" )
             ->setAttribute ( "onclick", "$( \".help\" ).toggle ( \"fold\", \"{}\", 500 );")
             ->getControlPrototype()->addClass('btn btn-secondary');

        $send = $form->addSubmit('send', 'Exportovat');
        $send->onClick[] = $this->exportFormSucceded;
        $send->getControlPrototype()->addClass('btn btn-primary');
             


       if ($this->exportFormOptions) {
            $form->setDefaults($this->exportFormOptions);
       }

        return $form;
    }


    public function exportFormSucceded($button)
    {  
        $form = $button->form;
        // some validations

        // setting session with form data via assoc array
        // redirect to this same page
        // where the results are displayed from session
        if ($form->isSuccess())
        {
            // form validation is ok
            // save the data to the session and redirect the page
            $this->getSession("export")->formOptions = $form->values;
            $this->redirect("Export:export" );
        }
    }

    protected function prepare() {
        if (!$this->getUser()->isLoggedIn())
            $this->Redirect("Sign:default");

        if(!$this->exportFormOptions) {
            $this->flashMessage("Vypršelo k sezení, zadejte export znovu.", "danger");
            $this->redirect("Calendar:viewCalendarEvents", [ "id" => $this->calendarId ]);
        }

    }

    protected function generateEmptyDays ()
    {
        if ( ! count($this->events->events) )
            return;

        $events = $this->events;
        $first = array_slice($events->events, 0, 1, true); $first = $first[key($first)];
        $last = array_slice($events->events, -1, 1, true); $last = $last[key($last)];
        $date = clone $events->start;
        $evs = clone $events;
        for ( $i = $events->start->Render("j"); $i < $first->m_Start->Render("j"); $i ++, $date->addDays(1) ) {
            $events->events [] = new \Event( clone $date, clone $date, $dummy = true );
        }
        $prev = -1;
        foreach ( $evs->events as $k => $event ) {
            for ( ; $i < $event->m_Start->Render("j"); $i ++, $date->addDays(1) ) {
                $events->events [] = new \Event( clone $date, clone $date, $dummy = true );
            }
            $i += $prev == $event->m_Start->Render("j") ? 0 : 1;
            $date->addDays( $prev == $event->m_Start->Render("j") ? 0 : 1 );
            $prev = $event->m_Start->Render("j");
        }
        for ( ; $i <= $events->end->Render("j"); $i ++, $date->addDays(1) ) {
            $events->events [] = new \Event( clone $date, clone $date, $dummy = true );
        }
        $events->applySort();
    }


    protected function perform() {
        $this->calendar = $this->googleService->getCalendar($this->calendarId);
        $events = $this->googleService->getEvents($this->calendarId, $this->calendarSearchOptions);


        $this->events = $events["events"];
        $this->template->events = $this->events;

        if($this->calendarSearchOptions && $this->calendarSearchOptions->join_days) {
            $this->events->joinDays($this->calendarSearchOptions && $this->calendarSearchOptions->workmode);
        }

        if($this->exportFormOptions->basic->empty_days) {
            $this->generateEmptyDays();
        }

    }

    public function renderExport () {
        $this->prepare();
        try {
            $this->perform();
            $formater = $this->supportedFormats[$this->exportFormOptions->basic->format];
            $formater->setEvents($this->events)
                     ->setCalendar($this->calendar)
                     ->setOptions($this->exportFormOptions)
                     ->updateTemplate($this->template);
            
        } catch ( \Exception $e ) {
            $this->flashMessage ( "Došlo k chybě při exportování událostí, zkuste to prosím znovu.", "danger" );
            $this->redirect("Index:default");
        }
    }

}