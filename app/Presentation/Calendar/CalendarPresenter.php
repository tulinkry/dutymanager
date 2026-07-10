<?php

namespace App\Presentation\Calendar;

use App\Presentation\BasePresenter;
use App\Model\Calendar\CalendarSearchFacade;
use App\Model\Google\GoogleCalendarService;
use App\Forms\SearchFormFactory;




/**
 * Kontroler pro obsluhu operací s kalendáøem jako je výpis událostí a vložení
 * nového kalendáøe.
 * @author Daemon
 * @version 1.0
 * @created 05-XII-2013 0:59:12
 */
class CalendarPresenter extends BasePresenter
{
    private $formOptions = NULL;

    public function __construct(
        private GoogleCalendarService $googleService,
        private CalendarSearchFacade $searchFacade,
        private SearchFormFactory $searchFormFactory,
    ) {
        parent::__construct();
    }

    /**
     * Vložení nového kalendáře.
     */
    public function renderAddCalendar()
    {
        try {
            if (!$this->getUser() || !$this->getUser()->isLoggedIn())
                $this->redirect('Sign:default');
        } catch(\Exception $e) { $this->redirect('Sign:default'); }
        // render add calendar form

        // on submit and validated
        // redirect to static page with some text
        // it is not necessarry to create another presenter method (i think :D)


    }

    protected function createComponentSearchForm()
    {
        $form = $this->searchFormFactory->create();

        if (isset($this->getSession('search')['form_data'])) {
            $form->setValues($this->getSession('search')['form_data']);
        }

        // call method signInFormSucceeded() on success
        $form->onSuccess[] = [$this, 'searchFormSucceeded'];
        return $form;
    }


    public function searchFormSucceeded($form)
    {
        // some validations

        // setting session with form data via assoc array
        // redirect to this same page
        // where the results are displayed from session
        if ($form->isSuccess())
        {
            // form validation is ok
            // save the data to the session and redirect the page
            $session = $this->getSession('search');
            $session['form_data'] = $this->formOptions = $form->getValues();
            if ( isset ( $session [ "calendar_id" ] ) && $session [ "calendar_id" ] != "" )
              $this->redirect('Calendar:viewCalendarEvents', $session [ "calendar_id" ] );
        }
    }


    public function actionViewCalendarEvents($id) {
        $this->template->alerts = [];
        if($this->getSession()->hasSection('alerts')) {
            $this->template->alerts = $this->getSession('alerts')->alerts;
        }
    }

    protected function prepare($id) {
        if (!$this->getUser()->isLoggedIn())
            $this->redirect('Sign:default');

        $session = $this->getSession('search');
        $session [ "calendar_id" ] = $id;
        if(isset($session["form_data"])) {
            $this->formOptions = $session["form_data"];
        }


        $this -> template -> events = [];
        $this->template->calendar = null;
        $this->template->week_accuracy = true;
    }

    protected function perform($id) {
            $this->template->calendar = $this->googleService->getCalendar($id);
            if($this->formOptions) {
                $this->formOptions->tax_params = $this->searchFacade->getTaxParams();
            }
            $events = $this->googleService->getEvents($id, $this->formOptions);


            $this -> template -> week_events = $events["week_events"];
            $this -> template -> events = $events["events"];


            if($this->formOptions && $this->formOptions->taxes && $this->formOptions->week_summary) {
                $this->template->week_accuracy = false;
            }

            $this->searchFacade->applyJoinDays($this->template->events, $this->formOptions);

    }

    /**
     * Seznam vyfiltrovaných událostí.
     */
    public function renderViewCalendarEvents($id)
    {
        $this->prepare($id);

        try {

            $this->perform($id);

        } catch ( \Exception $e ) {
            $this -> flashMessage ( "Nepodařilo se kontaktovat Google Kalendář, zkuste to prosím znovu za pár minut." );
        }


    }

    /**
     * render printable events
     */
    public function renderPrintCalendarEvents ( $id, $mode = 0 )
    {
        $this->prepare($id);

        $this -> template -> mode = $mode;

        try {

            $this->perform($id);

        } catch ( \Exception $e ) {
            $this -> flashMessage ( "Nepodařilo se kontaktovat Google Kalendář, zkuste to prosím znovu za pár minut." );
        }

    }


}
