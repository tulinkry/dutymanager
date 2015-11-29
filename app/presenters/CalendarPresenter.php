<?php


use Tulinkry\Application\UI;
use Tulinkry\Application\UI\Form;
use Nette\ArrayHash;




/**
 * Kontroler pro obsluhu operací s kalendáøem jako je výpis událostí a vložení
 * nového kalendáøe.
 * @author Daemon
 * @version 1.0
 * @created 05-XII-2013 0:59:12
 */
class CalendarPresenter extends BasePresenter
{
    /** @inject @var Tulinkry\Google\GoogleCalendarService */
    public $googleService;

    private $formOptions = NULL;

    private $taxes_defaults = array (
        'social_employer' => 0.25,
        'health_employer' => 0.09,
        'social_employee' => 0.065,
        'health_employee' => 0.045,

        'student' => true,
        'children' => 0,
        'tax' => 0.15,
        'ztp' => false,
        'retirement_lvl' => 0,
    );

    private $tax_params = array (
        'personal' => 24840,
        'student' => 4020,
        '1_child' => 13404,
        '2_child' => 15804,
        '3_child' => 17004,
        'other_child' => 17004,
        'ztp' => 16140,
        '1_inv' => 2520,
        '2_inv' => 2520,
        '3_inv' => 5040,

    );

    /**
     * Vložení nového kalendáře.
     */
    public function renderAddCalendar()
    {
        try {
            if (!$this->getUser() || !$this->getUser()->isLoggedIn())
                $this->Redirect("Sign:default");
        } catch(Exception $e) { $this->Redirect("Sign:default"); }
        // render add calendar form

        // on submit and validated
        // redirect to static page with some text
        // it is not necessarry to create another presenter method (i think :D)


    }

    protected function createComponentSearchForm()
    {
        /**
         * not full functionality !!!
         */

        $from_time = new iCalDate( strtotime( date ( 'Y-m' ) . "-01 00:00:00" ) );
        $to_time = new iCalDate ( strtotime ( date ('Y-m-') . $from_time->DaysInMonth( date('m'), date('Y') )." 23:59:59" ));

        $form = new UI\Form;
        $form -> addGroup ("Parametry hledání");

        $nameContainer = $form->addContainer('name_container');
        $nameContainer->addRadioList('match_type', 'Typ porovnávání:', array('Regulární výraz', 'Přesný výraz'))
                      ->setAttribute ( "title", "Zvolte typ prohledávání názvů událostí. Prohledává se pomocí shodného řetězce nebo regulárního výrazu." )
                      //->setHtml ('<a href=\"http://cs.wikipedia.org/wiki/Regul%C3%A1rn%C3%AD_v%C3%BDraz\">')
                      ->setValue(0);
        $nameContainer->addText('text_match', 'Hledaný výraz:')
                      ->setAttribute ( "title", "Vyplňte hledaný výraz." )
                      ->getControlPrototype()->addClass('form-control');

        $valueContainer = $form->addContainer('value_container');
        $valueContainer->addRadioList('price_type', 'Typ Ceny:', array('Z popisku', 'Paušálně', 'Kombinovaně', "Kombinovaně s přičítáním"))
                        -> setAttribute ( "title", "Zvolte algoritmus výpočtu celkové ceny. Paušálně se vypočítává ze zadané hodinové taxy a délky události. Z popisku 
                        se vypočítává, pokud popis události obsahuje číselnou hodnotu. Oba způsoby lze zkombinovat buď s předností ceny v popisku nebo příčítáním ceny v popisku k ceně paušální." )
                        -> setValue(2);
        $valueContainer->addText('price', 'Hodinová taxa')
                       ->setAttribute ( "title", "Zadejte hodinovou taxu v případě, že používáte paušální nebo kombinované oceňování." )
                       ->addConditionOn($valueContainer['price_type'], UI\Form::IS_IN, array(1, 2, 3))
                       ->addRule(UI\Form::FILLED, 'Zadejte cenu');

        $valueContainer['price']->getControlPrototype()->addClass('form-control');

       $form->addText('from_time', 'Od')
            ->setDefaultValue( $from_time -> Render () )
            ->setAttribute ( "title", "Vyplňte okamžik, od kterého se v kalendáři vyhledává." )
            ->getControlPrototype()->addClass('form-control');
       $form->addText('to_time', 'Do')
            ->setDefaultValue( $to_time -> Render () )
            ->setAttribute ( "title", "Vyplňte okamžik, do kterého se v kalendáři vyhledává." )
            ->getControlPrototype()->addClass('form-control');

        // select jestli se hledá paušálně
        // nebo pouze z políčka description
        // nebo kombinovaně

        $form->addCheckBox ( 'workmode', "Odečíst pracovní pauzy" )
             ->setDefaultValue ( false )
             ->setAttribute('class','switch')
             ->setAttribute('data-on-text', 'Ano')
             ->setAttribute('data-off-text', 'Ne')
             ->setAttribute ( "title", "Zaškrtněte v případě, že chcete z ceny odečíst povinné pracovní pauzy po 6 hodinách." );   

        $form->addCheckBox ( 'week_summary', "Provést týdenní součty" )
             ->setDefaultValue ( true )
             ->setAttribute('class','switch')
             ->setAttribute('data-on-text', 'Ano')
             ->setAttribute('data-off-text', 'Ne')
             ->setAttribute ( "title", "Zaškrtněte v případě, že chcete oříznout interval na celé týdny a propočítat tak týdenní částečné součty" );   
        
        $form->addCheckBox ( 'join_days', "Seskupit na dny" )
             ->setDefaultValue ( true )
             ->setAttribute('class','switch')
             ->setAttribute('data-on-text', 'Ano')
             ->setAttribute('data-off-text', 'Ne')
             ->setAttribute ( "title", "Zaškrtněte v případě, že chcete více událostí z jednoho dne seskupit do jedné události s výpisem přestávek" ); 

        $form->addCheckBox ( 'taxes', "Odečíst daně" )
             ->setDefaultValue ( false )
             ->setAttribute('class','switch')
             ->setAttribute('data-on-text', 'Ano')
             ->setAttribute('data-off-text', 'Ne')
             ->setAttribute ( "title", "Zaškrtněte v případě, že chcete z ceny odečíst daně." )
             ->addCondition ( $form::EQUAL, TRUE )
                 ->toggle("searchForm-taxes-id");         

        $form -> addGroup ( "Daňové parametry" )
              ->setOption('container', \Nette\Utils\Html::el('div')->id('searchForm-taxes-id'));
        $taxes = $form -> addContainer ( "taxes_container" );

        $taxes -> addCheckBox ( 'agreement', "Podepsané daňové prohlášení" )
               -> setDefaultValue ( true )
               -> setAttribute('class','switch')
               -> setAttribute('data-on-text', 'Ano')
               -> setAttribute('data-off-text', 'Ne')
               -> setAttribute ( "title", "Zaškrtněte v případě, že jste máte podepsané daňové prohlášení" );

        $taxes -> addText ( 'social_employer', 'Sociální pojištění - zaměstnavatel' )
               -> addRule ( $form::FLOAT, "Pole 'Sociální pojištění - zaměstnavatel' musí být číselné." )
               -> addConditionOn ( $form['taxes'], $form::EQUAL, TRUE )
                -> addRule ( $form::FILLED, 'Pole \'Sociální pojištění - zaměstnavatel\' musí být vyplněno' );
        $taxes -> addText ( 'health_employer', 'Zdravotní pojištění - zaměstnavatel' )
               -> addRule ( $form::FLOAT, "Pole 'Zdravotní pojištění - zaměstnavatel' musí být číselné." )
               -> addConditionOn ( $form['taxes'], $form::EQUAL, TRUE )
                -> addRule ( $form::FILLED, 'Pole \'Zdravotní pojištění - zaměstnavatel\' musí být vyplněno' );
        $taxes -> addText ( 'social_employee', 'Sociální pojištění - zaměstnanec' )
               -> addRule ( $form::FLOAT, "Pole 'Sociální pojištění - zaměstnanec' musí být číselné." )
               -> addConditionOn ( $form['taxes'], $form::EQUAL, TRUE )
                -> addRule ( $form::FILLED, 'Pole \'Sociální pojištění - zaměstnanec\' musí být vyplněno' );
        $taxes -> addText ( 'health_employee', 'Zdravotní pojištění - zaměstnanec' )
               -> addRule ( $form::FLOAT, "Pole Zdravotní pojištění - zaměstnanec' musí být číselné." )
               -> addConditionOn ( $form['taxes'], $form::EQUAL, TRUE )
                -> addRule ( $form::FILLED, 'Pole \'Zdravotní pojištění - zaměstnanec\' musí být vyplněno' );
        $taxes -> addCheckBox ( 'student', "Student" )
               -> setDefaultValue ( false )
               -> setAttribute('class','switch')
               -> setAttribute('data-on-text', 'Ano')
               -> setAttribute('data-off-text', 'Ne')
               -> setAttribute ( "title", "Zaškrtněte v případě, že jste student" );

        $taxes -> addCheckBox ( 'ztp', "Držitel průkazu ZTP/P" )
               -> setDefaultValue ( false )
               -> setAttribute('class','switch')
               -> setAttribute('data-on-text', 'Ano')
               -> setAttribute('data-off-text', 'Ne')
               -> setAttribute ( "title", "Zaškrtněte v případě, že jste držitel průkazu ZTP/P" );

        $taxes -> addSelect( "children", "Počet dětí" )
               -> setItems ( array ( 0 => "Žádné",
                                     1 => "1",
                                     2 => "2",
                                     3 => "3",
                                     4 => "4",
                                     5 => "5" ))
               -> addConditionOn ( $form['taxes'], $form::EQUAL, TRUE )
                -> addRule ( $form::FILLED, 'Počet dětí musí být vyplněn' );

        $taxes -> addSelect( "retirement_lvl", "Invalidní důchod" )
               -> setItems ( array ( 0 => "Žádný",
                                     1 => "1. stupně",
                                     2 => "2. stupně",
                                     3 => "3. stupně" ))
               -> addConditionOn ( $form['taxes'], $form::EQUAL, TRUE )
                -> addRule ( $form::FILLED, 'Invalidní důchod musí být vyplněn' );

        $taxes -> addText ( "tax", "Daň" )
               -> addRule ( $form::FLOAT, "Pole musí být číselné." )
               -> addConditionOn ( $form['taxes'], $form::EQUAL, TRUE )
                -> addRule ( $form::FILLED, 'Daň musí být vyplněna' );

        $taxes -> setDefaults ( $this->taxes_defaults );


        $form -> addGroup ();
        $form->addButton('help', 'Help')
             ->setAttribute ( "id", "help" )
             ->setAttribute ( "onclick", "$( \".help\" ).toggle ( \"fold\", \"{}\", 500 );")
             ->getControlPrototype()->addClass('btn btn-secondary');

        $form->addSubmit('send', 'Vyhledat')
             ->getControlPrototype()->addClass('btn btn-primary');


       if (isset($_SESSION['form_data'])) {
            $form->setValues($_SESSION['form_data']);
       }

        // call method signInFormSucceeded() on success
        $form->onSuccess[] = $this->searchFormSucceeded;
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
            $_SESSION['form_data'] = $this->formOptions = $form->getValues();
            if ( isset ( $_SESSION [ "calendar_id" ] ) && $_SESSION [ "calendar_id" ] != "" )
              $this->Redirect("Calendar:viewCalendarEvents", $_SESSION [ "calendar_id" ] );
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
            $this->Redirect("Sign:default");

        $_SESSION [ "calendar_id" ] = $id;
        if(isset($_SESSION["form_data"])) {
            $this->formOptions = $_SESSION["form_data"];
        }


        $this -> template -> events = [];
        $this->template->calendar = null;
        $this->template->week_accuracy = true;
    }

    protected function perform($id) {
            $this->template->calendar = $this->googleService->getCalendar($id);
            if($this->formOptions) {
                $this->formOptions->tax_params = $this->tax_params;
            }
            $events = $this->googleService->getEvents($id, $this->formOptions);

            
            $this -> template -> week_events = $events["week_events"];
            $this -> template -> events = $events["events"];


            if($this->formOptions && $this->formOptions->taxes && $this->formOptions->week_summary) {
                $this->template->week_accuracy = false;
            }

            if($this->formOptions && $this->formOptions->join_days) {
                $this->template->events->joinDays($this->formOptions && $this->formOptions->workmode);
            }

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
