<?php

namespace App\Forms;

use App\Model\Export;
use App\Model\Utils\Arrays;
use Nette\Application\UI\Form;

/**
 * Builds ExportPresenter's export form - extracted out of the presenter per
 * Nette's guidance to build complex/shared forms in a factory class
 * (https://doc.nette.org/en/forms/in-presenter). The replicators' "remove"
 * onClick handlers call ExportPresenter::forgetFieldColumn()/forgetSummaryColumn()
 * instead of poking $exportFormOptions directly, since that property is private
 * on the presenter and a closure defined here is scoped to this class, not to
 * ExportPresenter. Session-based defaults and the onSuccess handler stay in the
 * presenter, since both need $this.
 */
class ExportFormFactory
{
    /** @param array<string,object> $supportedFormats map of format key => formatter (must expose getName()) */
    public function create(array $supportedFormats): Form
    {
        $form = new Form;
        $form -> addGroup ("Formát");

        $basic = $form -> addContainer ("basic");

        // No explicit Bootstrap classes on the "basic" group's controls below - the
        // export template renders this group via $form->getRenderer()->renderPair(),
        // never through Tulinkry\Forms\Form::render()'s attachClasses() step, so these
        // never actually had 'form-control' styling in the live app. Preserving that
        // (rather than "fixing" it here) avoids an unrelated, unapproved visual change.
        $basic -> addSelect ( "format", "Formát", array_map(function($a) { return $a->getName(); }, $supportedFormats) )
              -> setPrompt ( "Zvolte výstupní formát" )
              -> setRequired ( "Zvolte výstupní formát" );

        $basic -> addText ( "date_format", "Formát data (sloupec datum)" )
              -> setAttribute ( "placeholder", "j. m. Y H:i// @see date()" )
              -> setDefaultValue ( "j. m. Y H:i" )
              -> addRule ( $form::FILLED, "Formát data (sloupec datum) nesmí být prázdný" )
              -> getControlPrototype()->addClass('text');

        $basic -> addText ( "block_format", "Formát data (sloupec začátek a konec)" )
              -> setAttribute ( "placeholder", "H:i// @see date()" )
              -> setDefaultValue ( "H:i" )
              -> addRule ( $form::FILLED, "Formát data (sloupec začátek a konec) nesmí být prázdný" )
              -> getControlPrototype()->addClass('text');

        $basic -> addCheckbox ( "empty_days", "Zobrazit i prázdné dny" );
        $basic -> addCheckbox ( "breaks", "Zobrazit přestávky" );
        $basic -> addText ( "break_date_format", "Formát přestávek" )
              -> setAttribute ( "placeholder", "H:i // @see date()" )
              -> setDefaultValue ( "H:i" )
              -> addRule ( $form::FILLED, "Formát přestávek nesmí být prázdný" )
              -> getControlPrototype()->addClass('text');

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
            $container->addSelect("index", "Pořadí sloupce", Arrays::createSequence(1, count($columns), true) )
                      ->setAttribute("class", "column_index")
                      ->setDefaultValue($container->name + 1);
            $container->addSubmit('remove', 'Odstranit')
                ->setValidationScope(null)
                ->setAttribute( "class", "btn btn-danger ajax")
                ->onClick[] = function($button) {
                    $button->getForm()->getPresenter()->redrawControl("exportForm");
                    $button->getForm()->getPresenter()->forgetFieldColumn($button->getParent()->getName());
                    $button->getParent()->getParent()->remove($button->getParent(), TRUE);
                };

        }, 0 );

        $replicator->addSubmit("add", "Přidat sloupec")
                   ->setAttribute( "class", "btn btn-success ajax")
                   ->setValidationScope(null)
                   ->onClick[] = function($button) {
                        $button->getForm()->getPresenter()->redrawControl("exportForm");
                        $button->getParent()->createOne();
                   };

        $form->addGroup("Resumé");
        $columns = Export\Exporter::getSummaryColumns();
        $replicator = $form->addDynamic("summary", function($container) use ($columns) {
            $container->addSelect("type", "Sloupec", $columns);
            $container->addSelect("index", "Pořadí sloupce", Arrays::createSequence(1, count($columns), true) )
                      ->setAttribute("class", "column_index")
                      ->setDefaultValue($container->name + 1);
            $container->addSubmit('remove', 'Odstranit')
                ->setValidationScope(null)
                ->setAttribute( "class", "btn btn-danger ajax")
                ->onClick[] = function($button) {
                    $button->getForm()->getPresenter()->redrawControl("exportForm");
                    $button->getForm()->getPresenter()->forgetSummaryColumn($button->getParent()->getName());
                    $button->getParent()->getParent()->remove($button->getParent(), TRUE);
                };

        }, 0 );

        $replicator->addSubmit("add", "Přidat sloupec")
                   ->setAttribute( "class", "btn btn-success ajax")
                   ->setValidationScope(null)
                   ->onClick[] = function($button) {
                        $button->getForm()->getPresenter()->redrawControl("exportForm");
                        $button->getParent()->createOne();
                   };

        $form -> addGroup ();
        $form->addButton('help', 'Help')
             ->setAttribute ( "id", "help" )
             ->setAttribute ( "onclick", "$( \".help\" ).toggle ( \"fold\", \"{}\", 500 );")
             ->getControlPrototype()->addClass('btn btn-secondary');

        $send = $form->addSubmit('send', 'Exportovat');
        $send->getControlPrototype()->addClass('btn btn-primary');

        return $form;
    }
}
