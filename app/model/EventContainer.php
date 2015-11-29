<?php

namespace Tulinkry\Google;

use iCalDate;

class EventContainer extends \StdClass {

    const MONTHS = 12;

    public $duration = 0;
    public $weeks = array ();
    public $prices = array ();
    public $events = array ();
    public $price = 0;
    public $start = null;
    public $end = null;
    public $taxed = false;
    public $count = 0;
    public $hour_tax = 0.0;

    public function __clone ()
    {
        foreach ($this->events as $key => $event){
            $this->events[$key] = clone $event;
        }
    }

    public function __construct ( $events ) 
    {

        $this->events = $events;

        foreach ( $events as $key => $event ) {
            $this->price += $event->m_Price;
            $this->duration += $event->m_Duration;

            if (isset($this->weeks[$event->m_Start->Week()]))
                $this->weeks[$event->m_Start->Week()] += $event->m_Duration;
            else
                $this->weeks[$event->m_Start->Week()] = $event->m_Duration;

            if (isset($this->prices[$event->m_Start->Week()]))
                $this->prices[$event->m_Start->Week()] += $event->m_Price;
            else
                $this->prices[$event->m_Start->Week()] = $event->m_Price;
        }
        $this->count = count($events);
        $this->hour_tax = $this->duration ? round($this->price / $this->duration, 2) : 0;
    }


    public function filter () {
        $self = $this;
        $_events = $self->events;
        $self->price = 0;
        $self->duration = 0;
        foreach ($_events as $key => $event) {
            if (strtotime($event->m_Start->Render()) < strtotime($self->start->Render()))
                unset($_events[$key]);
        }

        foreach ($_events as $key => $event) {
            if (strtotime($event->m_End->Render ()) > strtotime($self->end->Render())){
                unset($_events[$key]);    
                continue;   
            }
            $self->price += $event->m_Price;
            $self->duration += $event->m_Duration;
        }

        $self->events = $_events;
        $self->count = count($_events);
        $this->hour_tax = $this->duration ? round($this->price / $this->duration, 2) : 0;
        return $self;                    
    }

    public function joinDays ($workmode = true) {
        $self = $this;

        //print_r ( $this->events );
        $_events = $self->events;
        $prev_day = -1;
        $prev_event = null;
        foreach ($_events as $key => $event) {

            $duration = $event->m_End->getTimestamp() - $event->m_Start->getTimestamp();
            if((bool)floor($duration / 6) && $workmode) {
                // split
                $num_breaks = floor (($duration / 3600) / 6);
                for ( $i = 0; $i < $num_breaks; $i ++ ) {
                    $breakStart = new iCalDate($event->m_Start->getTimestamp() + ($i+1) * ($duration / ($num_breaks+1)) );
                    $breakEnd = new iCalDate($event->m_Start->getTimestamp() + ($i+1) * ($duration / ($num_breaks+1)) + 30 * 60);
                    $event->addBreak(array (
                        $breakStart,
                        $breakEnd,
                    ));
                    // already has been procesed by getEvents ()
                    // $event->m_Duration = $event->m_Duration - (($breakEnd->getTimestamp() - $breakStart->getTimestamp()) / 3600);
                }
            }
            if($prev_day == $event->m_Start->Render('j')) {
                // squeeze
                if ( $prev_event->m_Start->LessThan ($event->m_End) ) {
                    $breakStart = $prev_event->m_End;
                    $breakEnd = $event->m_Start;
                    $prev_event->m_End = $event->m_End;
                } else {
                    $breakStart = $event->m_End;
                    $breakEnd = $prev_event->m_Start;
                    $event->m_End = $prev_event->m_End;
                    $prev_event = $event;
                }
                $prev_event->m_Duration = ($event->m_Duration + $prev_event->m_Duration); 
                $prev_event->addBreak(array (
                    $breakStart,
                    $breakEnd
                ));
                unset ( $_events [ $key ] );
                //echo "BYLA PAUZA";
            } else {
                $prev_event = $event;
            }
            $prev_day = $event->m_Start->Render('j');
        }

        $self->events = $_events;
        $self->count = count($_events);
        $this->hour_tax = $this->duration ? round($this->price / $this->duration, 2) : 0;
        return $this;
    }

    public function applySort ( $closure = null ) {
        if(! $closure ) {
            $closure = function ( $a, $b ) {
                return (strtotime($a->m_Start->Render()) - strtotime($b->m_Start->Render()));
            };
        }
        uasort($this->events, $closure);
        return $this;
    }

    public function applyTax ( $params ) {
        if(!$this->count || !$this->price) {
            return $this;
        }
        /*

            private $taxes_defaults = array (
                'social_employer' => 0.25,
                'health_employer' => 0.09,
                'social_employee' => 0.065,
                'health_employee' => 0.045,

                'student' => true,
                'children' => 0,
                'tax' => 0.15,
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
        */
        $self = $this;

        $values = $params["values"];
        $params = $params["params"];

        ////echo "salary: " .$self->price. "<br>";

        $social_employer = ceil($values["social_employer"] * $self->price);
        $health_employer = ceil($values["health_employer"] * $self->price);
        $social_employee = ceil($values["social_employee"] * $self->price);
        $health_employee = ceil($values["health_employee"] * $self->price);

        if($self->price < 2499) {
            $social_employer = 0;
            $health_employer = 0;
            $social_employee = 0;
            $health_employee = 0;
        }
        if($self->price > 2499 && $self->price < 9200 && ! $values['student']) {
            $health_employee = 1242 - ($self->price * $values['health_employer'] );
        }

        $employer = $social_employer + $health_employer;
        //echo "employer: $employer<br>";
        $superSalary = ceil(($employer+$self->price) / 100) * 100;
        //echo "superSalary: " .$superSalary. "<br>";

        $taxed = $superSalary * $values["tax"];

        ////echo "taxed: " .$taxed. "<br>";


        $sale = 0;
        $bonus = 0;
        if(isset($values["agreement"]) && $values["agreement"]) {
            // sleva
            $sale += $params['personal'] / self::MONTHS;
            $sale += $values['student'] ? $params['student'] / self::MONTHS : 0;
            $sale += $values['ztp'] ? $params['ztp'] / self::MONTHS: 0;
            $sale += $values['retirement_lvl'] == 1 ? $params['1_inv'] / self::MONTHS : 0;
            $sale += $values['retirement_lvl'] == 2 ? $params['2_inv'] / self::MONTHS : 0;
            $sale += $values['retirement_lvl'] == 3 ? $params['3_inv'] / self::MONTHS : 0;

            // zvyhodneni
            for ( $i = 1; $i <= ( 3 < $values["children"] ? 3 : $values["children"] ); $i ++ ) {
                $v = $i . "_child";
                $bonus += $params[$v] / self::MONTHS;
            }
            if ($values["children"] > 3){
                $bonus += (($values["children"] - 3) * $params["other_child"]) / self::MONTHS;
            }
        }
        ////echo "sale: " .$sale. "<br>";

        $self->sale = $sale;
        $self->bonus = $bonus;

        $zaloha = 0;

        //echo "sale: $sale<br>";
        //echo "bonus: $bonus<br>";
        //echo "taxed: $taxed<br>";

        if($taxed - $sale - $bonus > 0) {
        //echo "taxed: $taxed<br>";
            $zaloha = $taxed - $sale - $bonus;
        }

        if($zaloha === 0){
            $pridavek = ($superSalary * $values["tax"] - $params['personal'] / self::MONTHS)  < 0 ? $bonus : -1 * ($superSalary * $values["tax"] - $sale + $bonus);
        } else {
            $pridavek = 0;
        }

        if ( $pridavek < -(60630 / self::MONTHS) )
            $pridavek = -(60630 / self::MONTHS);

        ////echo $self->price ."<br>";
        ////echo $taxed ."<br>";
        ////echo ceil($values['health_employee'] * $self->price) ."<br>";
        ////echo ceil($values['social_employee'] * $self->price) ."<br>";

        $result = $self->price - $zaloha;
        $result -= $health_employee;
        $result -= $social_employee;
        $result += $pridavek;

        $coef = $result / $self->price;

        ////echo $coef ."<br>";

        $self->taxed = true;
        $self->taxinfo = new \StdClass;
        $self->taxinfo->coef = $coef;
        $self->taxinfo->sale = $sale;
        $self->taxinfo->tax = $superSalary * $values["tax"];
        $self->taxinfo->deposit = $zaloha;
        $self->taxinfo->bonus = $pridavek;
        $self->taxinfo->social_employee = $social_employee;
        $self->taxinfo->health_employee = $health_employee;
        $self->taxinfo->social_employer = $social_employer;
        $self->taxinfo->health_employer = $health_employer;

        $self -> price = $result;

        foreach ( $self->events as $key => $event ) {
            $event->m_Price = ceil($event->m_Price * $coef);
        }
        foreach ( $self->prices as $key => &$price ) {
            $price = ceil($price * $coef);
        }

        $self->hour_tax = $self->duration ? round($self->price / $self->duration, 2) : 0;

        return $self;
    }
};