<?php

namespace App\Forms;

use App\Model\Calendar\iCalDate;
use App\Model\Calendar\CalendarSearchFacade;
use Nette\Application\UI\Form;

/**
 * Builds CalendarPresenter's search form - extracted out of the presenter per
 * Nette's guidance to build complex/shared forms in a factory class
 * (https://doc.nette.org/en/forms/in-presenter). Session-based value restoration
 * and the onSuccess handler stay in the presenter, since both need $this.
 */
class SearchFormFactory
{
	public function __construct(
		private CalendarSearchFacade $searchFacade,
	) {
	}

	public function create(): Form
	{
		$from_time = new iCalDate( strtotime( date ( 'Y-m' ) . "-01 00:00:00" ) );
		$to_time = new iCalDate ( strtotime ( date ('Y-m-') . $from_time->DaysInMonth( date('m'), date('Y') )." 23:59:59" ));

		$form = new Form;
		$form -> addGroup ("Parametry hledání");

		$nameContainer = $form->addContainer('name_container');
		$nameContainer->addRadioList('match_type', 'Typ porovnávání:', array('Regulární výraz', 'Přesný výraz'))
					  ->setAttribute ( "title", "Zvolte typ prohledávání názvů událostí. Prohledává se pomocí shodného řetězce nebo regulárního výrazu." )
					  ->setValue(0);
		$nameContainer->addText('text_match', 'Hledaný výraz:')
					  ->setAttribute ( "title", "Vyplňte hledaný výraz." )
					  ->getControlPrototype()->addClass('form-control text');

		$valueContainer = $form->addContainer('value_container');
		$valueContainer->addRadioList('price_type', 'Typ Ceny:', array('Z popisku', 'Paušálně', 'Kombinovaně', "Kombinovaně s přičítáním"))
						-> setAttribute ( "title", "Zvolte algoritmus výpočtu celkové ceny. Paušálně se vypočítává ze zadané hodinové taxy a délky události. Z popisku
						se vypočítává, pokud popis události obsahuje číselnou hodnotu. Oba způsoby lze zkombinovat buď s předností ceny v popisku nebo příčítáním ceny v popisku k ceně paušální." )
						-> setValue(2);
		$valueContainer->addText('price', 'Hodinová taxa')
					   ->setAttribute ( "title", "Zadejte hodinovou taxu v případě, že používáte paušální nebo kombinované oceňování." )
					   ->addConditionOn($valueContainer['price_type'], Form::IS_IN, array(1, 2, 3))
					   ->addRule(Form::FILLED, 'Zadejte cenu');

		$valueContainer['price']->getControlPrototype()->addClass('form-control text');

	   $form->addText('from_time', 'Od')
			->setDefaultValue( $from_time -> Render () )
			->setAttribute ( "title", "Vyplňte okamžik, od kterého se v kalendáři vyhledává." )
			->getControlPrototype()
				->id('frmsearchForm-from_time')
				->addClass('form-control text');
	   $form->addText('to_time', 'Do')
			->setDefaultValue( $to_time -> Render () )
			->setAttribute ( "title", "Vyplňte okamžik, do kterého se v kalendáři vyhledává." )
			->getControlPrototype()
				->id('frmsearchForm-to_time')
				->addClass('form-control text');

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
		$taxes['social_employer']->getControlPrototype()->addClass('form-control text');
		$taxes -> addText ( 'health_employer', 'Zdravotní pojištění - zaměstnavatel' )
			   -> addRule ( $form::FLOAT, "Pole 'Zdravotní pojištění - zaměstnavatel' musí být číselné." )
			   -> addConditionOn ( $form['taxes'], $form::EQUAL, TRUE )
				-> addRule ( $form::FILLED, 'Pole \'Zdravotní pojištění - zaměstnavatel\' musí být vyplněno' );
		$taxes['health_employer']->getControlPrototype()->addClass('form-control text');
		$taxes -> addText ( 'social_employee', 'Sociální pojištění - zaměstnanec' )
			   -> addRule ( $form::FLOAT, "Pole 'Sociální pojištění - zaměstnanec' musí být číselné." )
			   -> addConditionOn ( $form['taxes'], $form::EQUAL, TRUE )
				-> addRule ( $form::FILLED, 'Pole \'Sociální pojištění - zaměstnanec\' musí být vyplněno' );
		$taxes['social_employee']->getControlPrototype()->addClass('form-control text');
		$taxes -> addText ( 'health_employee', 'Zdravotní pojištění - zaměstnanec' )
			   -> addRule ( $form::FLOAT, "Pole Zdravotní pojištění - zaměstnanec' musí být číselné." )
			   -> addConditionOn ( $form['taxes'], $form::EQUAL, TRUE )
				-> addRule ( $form::FILLED, 'Pole \'Zdravotní pojištění - zaměstnanec\' musí být vyplněno' );
		$taxes['health_employee']->getControlPrototype()->addClass('form-control text');
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
		$taxes['children']->getControlPrototype()->addClass('form-control');

		$taxes -> addSelect( "retirement_lvl", "Invalidní důchod" )
			   -> setItems ( array ( 0 => "Žádný",
									 1 => "1. stupně",
									 2 => "2. stupně",
									 3 => "3. stupně" ))
			   -> addConditionOn ( $form['taxes'], $form::EQUAL, TRUE )
				-> addRule ( $form::FILLED, 'Invalidní důchod musí být vyplněn' );
		$taxes['retirement_lvl']->getControlPrototype()->addClass('form-control');

		$taxes -> addText ( "tax", "Daň" )
			   -> addRule ( $form::FLOAT, "Pole musí být číselné." )
			   -> addConditionOn ( $form['taxes'], $form::EQUAL, TRUE )
				-> addRule ( $form::FILLED, 'Daň musí být vyplněna' );
		$taxes['tax']->getControlPrototype()->addClass('form-control text');

		$taxes -> setDefaults ( $this->searchFacade->getTaxesDefaults() );


		$form -> addGroup ();
		$form->addButton('help', 'Help')
			 ->setAttribute ( "id", "help" )
			 ->setAttribute ( "onclick", "$( \".help\" ).toggle ( \"fold\", \"{}\", 500 );")
			 ->getControlPrototype()->addClass('btn btn-secondary button');

		$form->addSubmit('send', 'Vyhledat')
			 ->getControlPrototype()->addClass('btn btn-primary button');

		return $form;
	}
}
