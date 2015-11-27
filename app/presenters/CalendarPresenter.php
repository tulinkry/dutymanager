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


       if (isset($_SESSION['form_data']))
            $form->setValues($_SESSION['form_data']);

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

    /**
     * Seznam vyfiltrovaných událostí.
     */
    public function renderViewCalendarEvents($id)
    {
        if (!$this->getUser()->isLoggedIn())
            $this->Redirect("Sign:default");

        $_SESSION [ "calendar_id" ] = $id;


        global $calendarApi;

        $this -> template -> events = [];
        $this->template->calendar = null;
        $this->template->week_accuracy = true;


        try {

            $this->template->calendar = $calendarApi->calendars->get($id);
            $events = $this -> getEvents ( $id );

            
            $this -> template -> week_events = clone $events;
            $this -> template -> events = $events->filter();

            foreach ($events->events as $key => $event)
            {
                $event->m_Description = substr ( $event->m_Description, 0, 10 ); // short the description if neccessary
            }

            if($this->formOptions && $this->formOptions->taxes) {
                $tax = array ( "values" => $this->formOptions->taxes_container, 
                               "params" => $this->tax_params );
                $events->applyTax($tax);
                $this -> template -> week_events->applyTax($tax);
            }

            if($this->formOptions && $this->formOptions->taxes && $this->formOptions->week_summary) {
                $this->template->week_accuracy = false;
            }

            $events->applySort(__CLASS__."::cmpfunc");

        } catch ( \Exception $e ) {
            $this -> flashMessage ( "Nepodařilo se kontaktovat Google Kalendář, zkuste to prosím znovu za pár minut." );
        }


    }

    /**
     * render printable events
     */
    public function renderPrintCalendarEvents ( $id, $mode = 0 )
    {
        if (!$this->getUser()->isLoggedIn())
            $this->Redirect("Sign:default");
            
        $_SESSION [ "calendar_id" ] = $id;
        $this -> template -> mode = $mode;
       
        $from_time = new iCalDate( strtotime( date ( 'Y-m' ) . "-01 00:00:00" ) );
        $to_time = new iCalDate ( strtotime ( date ('Y-m-') . $from_time->DaysInMonth( date('m'), date('Y') )." 23:59:59" ));

        //$this -> template -> hour_tax = 0.0;


        $this -> template -> events = [];

        $this->template->calendar = null;

        global $calendarApi;


        $this -> template -> events = [];
        $this->template->calendar = null;

        try {

            $this->template->calendar = $calendarApi->calendars->get($id);
            $events = $this -> getEvents ( $id );

            
            $this -> template -> week_events = clone $events;
            $this -> template -> events = $events->filter();

            foreach ($events->events as $key => $event)
            {
                $event->m_Description = substr ( $event->m_Description, 0, 10 ); // short the description if neccessary
            }

            if($this->formOptions && $this->formOptions->taxes) {
                $tax = array ( "values" => $this->formOptions->taxes_container, 
                               "params" => $this->tax_params );
                $events->applyTax($tax);
                $this -> template -> week_events->applyTax($tax);
            }

            $events->applySort(__CLASS__."::cmpfunc");

        } catch ( \Exception $e ) {
            $this -> flashMessage ( "Nepodařilo se kontaktovat Google Kalendář, zkuste to prosím znovu za pár minut." );
        }

    }              

    public static function cmpfunc ( $a, $b )
    {
        // compare function of two dates
        return (strtotime($a->m_Start->Render()) - strtotime($b->m_Start->Render()));
    }

    protected function addEvent ( $event, $from_time, $to_time, &$array )
    {
        // check if it is recursive
        // add all occurencces between two dates

        $this->convertEvent ( $event );

        $ev = new Event ( $event->start, $event->end );
        $ev -> m_Id = $event -> id;
        $ev -> m_Summary = $event -> summary;
        $ev -> m_Price = 0;
        $ev -> m_Description = $event -> description;
        $ev -> m_Duration = (strtotime($ev->m_End->Render ()) - strtotime($ev->m_Start->Render())) / 3600;
        if ( $ev -> m_Start -> GreaterThan ( $from_time ) && $ev -> m_Start -> LessThan ( $to_time ) )
            // if event is between two times
            // add him to the array
            $array[] = $ev;
    }
    protected function convertEvent ( &$event )
    {
        // converts the start and end of the event to the 
        // iCalDate format
        // if some event doesnt have the datetime form of date
        // and has only the date form, it converts it

        if ( isset($event->start->dateTime) )
        {
            $event -> start = new iCalDate ( strtotime($event -> start -> dateTime) );
        } else
        {
            $event -> start = new iCalDate ( strtotime($event->start->date."T00:00:00") );
        }
        if ( isset($event->end->dateTime) )
        {
            $event -> end = new iCalDate (strtotime( $event -> end -> dateTime) );
        } else
        {
            $event -> end = new iCalDate ( strtotime($event->end->date."T00:00:00") );
            //$event -> end -> addDays ( 1 );
        }
    }
    
    protected function getEvents ( $id )
    {
    
        if ( isset ( $_SESSION [ "form_data" ] ) )
        {
          $this -> formOptions = $_SESSION [ "form_data" ];
        }     
    
        $from_time = new iCalDate( strtotime( date ( 'Y-m' ) . "-01 00:00:00" ) );
        $to_time = new iCalDate ( strtotime ( date ('Y-m-') . $from_time->DaysInMonth( date('m'), date('Y') )." 23:59:59" ));

        if ( $this->formOptions)
        {
            $from_time = new iCalDate ( strtotime($this->formOptions->from_time));
            $to_time = new iCalDate ( strtotime($this->formOptions->to_time));
            $price = 0;

        }

        $from_bounded_time = $from_time;
        $to_bounded_time = $to_time;

        if($this->formOptions && isset($this->formOptions->week_summary) && $this->formOptions->week_summary) {
            // add whole weeks
            $from_bounded_time = strtotime ( "-1 Monday", $from_time->_epoch );
            $from_bounded_time = new iCalDate ( strtotime ( date ("Y-m-d", $from_bounded_time)." 00:00:00" ) );
            $to_bounded_time = strtotime ( "+1 Sunday", $to_time->_epoch );
            $to_bounded_time = new iCalDate ( strtotime ( date ("Y-m-d", $to_bounded_time)." 23:59:59" ) );
        }

    
        global $calendarApi;
        $events = array ();
        $this->template->calendar = $calendarApi->calendars->get($id);
        $optParams = array ('timeMin' => $from_bounded_time->RenderGMT(DATE_ATOM),//$from_bounded_time->RenderGMT(),
                            'timeMax' => $to_bounded_time->RenderGMT(DATE_ATOM) );
        $googleEvents = $calendarApi->events->listEvents($id, $optParams);

        while ( 1 )
        {
            //print_r ( $googleEvents );
            foreach ( $googleEvents->getItems () as $key => $event )
            {
                if ( !isset( $event->start) || ! isset ( $event -> end ) )
                {
                    // unset ( $googleEvents [ $key ] );
                    // some bad event
                    continue;
                }
                
                if ( $event -> recurrence == null  )
                {
                  if ( $event -> recurringEventId == null )
                  {
                    $this -> addEvent ( $event, $from_bounded_time, $to_bounded_time, $events );
                  }
                }
                else
                {
                    // reccurrence event
                    // add all reccurrences
                    $optParams = array ( 'timeMin' => $from_bounded_time->RenderGMT(DATE_ATOM),//$from_bounded_time->RenderGMT(),
                                         'timeMax' => $to_bounded_time->RenderGMT(DATE_ATOM));//$to_bounded_time->RenderGMT());
                    $recEvents = $calendarApi->events->instances( $id, $event->id, $optParams );
                    while ( 1 )
                    {
                        //print_r ( $recEvents );
                        foreach ( $recEvents->getItems() as $klic => $hodnota )
                            $this -> addEvent ( $hodnota, $from_bounded_time, $to_bounded_time, $events );
                        $pgTkn = $recEvents->getNextPageToken ();
                        if ( $pgTkn )
                        {
                            $optP = array ( 'timeMin' => $from_bounded_time->RenderGMT(DATE_ATOM),//$from_bounded_time->RenderGMT(),
                                            'timeMax' => $to_bounded_time->RenderGMT(DATE_ATOM),
                                            'pageToken' => $pgTkn );//$to_bounded_time->RenderGMT());
                            $recEvents = $calendarApi->events->instances( $id, $event->id, $optParams );
                        }
                        else 
                            break;
                    }

                }
            }
            $pageToken = $googleEvents -> getNextPageToken ();
            if ( $pageToken )
            {
                $optParams = array ( 'pageToken' => $pageToken,
                                     'timeMin' => $from_bounded_time->RenderGMT(DATE_ATOM),//$from_bounded_time->RenderGMT(),
                                     'timeMax' => $to_bounded_time->RenderGMT(DATE_ATOM) );
                $googleEvents = $calendarApi->events->listEvents($id, $optParams);
            }
            else
                break;
        }


        if ($this->formOptions)
        {

            //$from_bounded_time = new iCalDate ( strtotime($this->formOptions->from_time));
            //$to_bounded_time = new iCalDate ( strtotime($this->formOptions->to_time));

            if (strlen($this->formOptions->name_container->text_match))
                switch (intval($this->formOptions->name_container->match_type))
                {
                    case 0: // RegExp
                        foreach ($events as $key => $event)
                            if (preg_match('/' . $this->formOptions->name_container->text_match . '/', $event->m_Summary) !== 1)
                                unset($events[$key]);
                        break;
                    case 1: // Exact Match
                        foreach ($events as $key => $event)
                            if ($this->formOptions->name_container->text_match != $event->m_Summary)
                                unset($events[$key]);
                        break;
                }

            /*
            if (strlen(trim($this->formOptions->from_bounded_time)))
                foreach ($events as $key => $event)
                    if (strtotime($event->m_Start->Render()) < strtotime($from_bounded_time->Render()))
                        unset($events[$key]);

            if (strlen(trim($this->formOptions->to_bounded_time)))
                foreach ($events as $key => $event)
                    if (strtotime($event->m_End->Render ()) > strtotime($to_bounded_time->Render()))
                        unset($events[$key]);
            */

            switch (intval($this->formOptions->value_container->price_type))
            {
                case 0: // Only Description
                    foreach ($events as $key => $event)
                        if (is_null($event->m_Description) || !strlen($event->m_Description))
                            unset($events[$key]);
                        else
                            $event->m_Price = floatval( $event->m_Description );
                     break;
                case 1: // Only paushal
                    if (is_null($this->formOptions->value_container->price))
                        break;
                    foreach ($events as $key => $event)
                        if (!is_null($event->m_Description))
                            unset($events[$key]);
                        else
                        {
                            //$event->description = //floatval($this->formOptions->value_container->price) *
                            $event->m_Price = (strtotime($event->m_End->Render ()) - strtotime($event->m_Start->Render())) / 3600;
                            if ( $this->formOptions->workmode )
                            {
                                // work pause every 6 hours
                                $event->m_Price -= floor($event->m_Price / 6) * 0.5; // work pause
                            }
                            $event->m_Duration = $event -> m_Price;
                            $event->m_Price *= floatval($this->formOptions->value_container->price);
                            $event->m_Price = (int)$event->m_Price;
                        }
                    break;
                case 2:
                    if (is_null($this->formOptions->value_container->price))
                        break;
                    foreach ($events as $key => $event)
                        if (is_null($event->m_Description))
                        {
                            $event->m_Price = (strtotime($event->m_End->Render ()) - strtotime($event->m_Start->Render())) / 3600;
                            if ( $this->formOptions->workmode )
                            {
                                // work pause every 6 hours
                                $event->m_Price -= floor($event->m_Price / 6) * 0.5; // work pause
                            }
                            $event->m_Duration = $event -> m_Price;
                            $event->m_Price *= floatval($this->formOptions->value_container->price);
                            $event->m_Price = (int)$event->m_Price;
                        }
                        else
                            $event->m_Price = floatval( $event->m_Description );
                    break;
                case 3: // Combined with adding
                    if (is_null($this->formOptions->value_container->price))
                        break;
                    foreach ($events as $key => $event) {
                        $event->m_Price = (strtotime($event->m_End->Render ()) - strtotime($event->m_Start->Render())) / 3600;
                        if ( $this->formOptions->workmode )
                        {
                            // work pause every 6 hours
                            $event->m_Price -= floor($event->m_Price / 6) * 0.5; // work pause
                        }
                        $event->m_Duration = $event -> m_Price;
                        $event->m_Price *= floatval($this->formOptions->value_container->price);
                        $event->m_Price = (int)$event->m_Price;

                        if(!is_null($event->m_Description))
                            $event->m_Price += floatval( $event->m_Description );
                    }

                    break;
            }
            
        }


        $r = new \EventContainer($events);
        $r -> start = $from_time;
        $r -> end = $to_time;
        
        return $r;
    }
}
