<?php

namespace App\Presentation\Export;

use App\Presentation\BasePresenter;
use App\Model\Calendar\CalendarSearchFacade;
use App\Model\Google\GoogleCalendarService;
use App\Model\Export\EmptyDayFiller;
use App\Forms\ExportFormFactory;
use Nette\ArrayHash;
use Nette\Application\Responses\TextResponse;
use App\Model\Export;

class ExportPresenter extends BasePresenter
{
    private $calendarSearchOptions = NULL;
    private $exportFormOptions = NULL;
    private $calendarId = 0;
    private $events = null;
    private $calendar = null;

    private $supportedFormats = array ();

    public function __construct(
        private GoogleCalendarService $googleService,
        private CalendarSearchFacade $searchFacade,
        private EmptyDayFiller $emptyDayFiller,
        private ExportFormFactory $exportFormFactory,
    ) {
        parent::__construct();
    }

    public function startup()
    {
        parent::startup();

        $this->supportedFormats = array (
            "csv" => new Export\Csv,
            "xml" => new Export\Xml,
        );

        $session = $this->getSession('search');

        if(!isset($session["calendar_id"]) || !$session["calendar_id"]) {
            $this->flashMessage("Došlo k chybě, patrně vám vypršelo aktuální sezení. Pokračujte výběrem kalendáře.", "danger" );
            $this->redirect("Index:default");
        }

        if(!isset($session["form_data"]) || !$session["form_data"]) {
            $this->flashMessage("Došlo k chybě, zřejmě jste nevybrali žádné události ve vyhledávání. Pokračujte hledáním událostí.", "danger" );
            $this->redirect("Calendar:viewCalendarEvents", [ "id" => $session["calendar_id"] ] );
        }

        $this->calendarSearchOptions = $session["form_data"];
        $this->calendarId = $session["calendar_id"];
        $this->template->calendarId = $this->calendarId;

        if($this->getSession()->hasSection("export") && isset($this->getSession("export")->formOptions)) {
            $this->exportFormOptions = $this->getSession("export")->formOptions;
        }
    }


    protected function createComponentExportForm()
    {
        $form = $this->exportFormFactory->create($this->supportedFormats);

        $form['send']->onClick[] = [$this, 'exportFormSucceded'];

        if ($this->exportFormOptions) {
            $form->setDefaults($this->exportFormOptions);
        }

        return $form;
    }


    /**
     * Called by the "fields" replicator's own "remove" button (see ExportFormFactory) -
     * kept as a public method rather than letting the form's onClick closures poke
     * $exportFormOptions directly, since that's a private property.
     */
    public function forgetFieldColumn(string $containerName): void
    {
        if ($this->exportFormOptions && isset($this->exportFormOptions->fields[$containerName])) {
            unset($this->exportFormOptions->fields[$containerName]);
        }
    }

    /** Same as forgetFieldColumn(), for the "summary" replicator. */
    public function forgetSummaryColumn(string $containerName): void
    {
        if ($this->exportFormOptions && isset($this->exportFormOptions->summary[$containerName])) {
            unset($this->exportFormOptions->summary[$containerName]);
        }
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
            $this->redirect("Sign:default");

        if(!$this->exportFormOptions) {
            $this->flashMessage("Vypršelo k sezení, zadejte export znovu.", "danger");
            $this->redirect("Calendar:viewCalendarEvents", [ "id" => $this->calendarId ]);
        }

    }

    protected function perform() {
        $this->calendar = $this->googleService->getCalendar($this->calendarId);
        $events = $this->googleService->getEvents($this->calendarId, $this->calendarSearchOptions);


        $this->events = $events["events"];
        $this->template->events = $this->events;

        $this->searchFacade->applyJoinDays($this->events, $this->calendarSearchOptions);

        if($this->exportFormOptions->basic->empty_days) {
            $this->emptyDayFiller->fill($this->events);
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