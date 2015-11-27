<?php


/**
* A Class for handling Events on a calendar which repeat
*
* Here's the spec, from RFC2445:
*
*     recur      = "FREQ"=freq *(
*
*                ; either UNTIL or COUNT may appear in a 'recur',
*                ; but UNTIL and COUNT MUST NOT occur in the same 'recur'
*
                ( ";" "UNTIL" "=" enddate ) /
                ( ";" "COUNT" "=" 1*DIGIT ) /

                ; the rest of these keywords are optional,
                ; but MUST NOT occur more than once

                ( ";" "INTERVAL" "=" 1*DIGIT )          /
                ( ";" "BYSECOND" "=" byseclist )        /
                ( ";" "BYMINUTE" "=" byminlist )        /
                ( ";" "BYHOUR" "=" byhrlist )           /
                ( ";" "BYDAY" "=" bywdaylist )          /
                ( ";" "BYMONTHDAY" "=" bymodaylist )    /
                ( ";" "BYYEARDAY" "=" byyrdaylist )     /
                ( ";" "BYWEEKNO" "=" bywknolist )       /
                ( ";" "BYMONTH" "=" bymolist )          /
                ( ";" "BYSETPOS" "=" bysplist )         /
                ( ";" "WKST" "=" weekday )              /
                ( ";" x-name "=" text )
                )

     freq       = "SECONDLY" / "MINUTELY" / "HOURLY" / "DAILY"
                / "WEEKLY" / "MONTHLY" / "YEARLY"

     enddate    = date
     enddate    =/ date-time            ;An UTC value

     byseclist  = seconds / ( seconds *("," seconds) )

     seconds    = 1DIGIT / 2DIGIT       ;0 to 59

     byminlist  = minutes / ( minutes *("," minutes) )

     minutes    = 1DIGIT / 2DIGIT       ;0 to 59

     byhrlist   = hour / ( hour *("," hour) )

     hour       = 1DIGIT / 2DIGIT       ;0 to 23

     bywdaylist = weekdaynum / ( weekdaynum *("," weekdaynum) )

     weekdaynum = [([plus] ordwk / minus ordwk)] weekday

     plus       = "+"

     minus      = "-"

     ordwk      = 1DIGIT / 2DIGIT       ;1 to 53

     weekday    = "SU" / "MO" / "TU" / "WE" / "TH" / "FR" / "SA"
     ;Corresponding to SUNDAY, MONDAY, TUESDAY, WEDNESDAY, THURSDAY,
     ;FRIDAY, SATURDAY and SUNDAY days of the week.

     bymodaylist = monthdaynum / ( monthdaynum *("," monthdaynum) )

     monthdaynum = ([plus] ordmoday) / (minus ordmoday)

     ordmoday   = 1DIGIT / 2DIGIT       ;1 to 31

     byyrdaylist = yeardaynum / ( yeardaynum *("," yeardaynum) )

     yeardaynum = ([plus] ordyrday) / (minus ordyrday)

     ordyrday   = 1DIGIT / 2DIGIT / 3DIGIT      ;1 to 366

     bywknolist = weeknum / ( weeknum *("," weeknum) )

     weeknum    = ([plus] ordwk) / (minus ordwk)

     bymolist   = monthnum / ( monthnum *("," monthnum) )

     monthnum   = 1DIGIT / 2DIGIT       ;1 to 12

     bysplist   = setposday / ( setposday *("," setposday) )

     setposday  = yeardaynum
*
* At this point we are going to restrict ourselves to parts of the RRULE specification
* seen in the wild.  And by "in the wild" I don't include within people's timezone
* definitions.  We always convert time zones to canonical names and assume the lower
* level libraries can do a better job with them than we can.
*
* We will concentrate on:
*  FREQ=(YEARLY|MONTHLY|WEEKLY|DAILY)
*  UNTIL=
*  COUNT=
*  INTERVAL=
*  BYDAY=
*  BYMONTHDAY=
*  BYSETPOS=
*  WKST=
*  BYYEARDAY=
*  BYWEEKNO=
*  BYMONTH=
*
*
* @package awl
*/
class RRule {
  /**#@+
  * @access private
  */

  /** The first instance */
  var $_first;

  /** The current instance pointer */
  var $_current;

  /** An array of all the dates so far */
  var $_dates;

  /** Whether we have calculated any of the dates */
  var $_started;

  /** Whether we have calculated all of the dates */
  var $_finished;

  /** The rule, in all it's glory */
  var $_rule;

  /** The rule, in all it's parts */
  var $_part;

  /**#@-*/

  /**
  * The constructor takes a start date and an RRULE definition.  Both of these
  * follow the iCalendar standard.
  */
  function RRule( $start, $rrule ) {
    $this->_first = new iCalDate($start);
    $this->_finished = false;
    $this->_started = false;
    $this->_dates = array();
    $this->_current = -1;

    $this->_rule = preg_replace( '/\s/m', '', $rrule);
    if ( substr($this->_rule, 0, 6) == 'RRULE:' ) {
      $this->_rule = substr($this->_rule, 6);
    }

    //dbg_error_log( "RRule", " new RRule: Start: %s, RRULE: %s", $start->Render(), $this->_rule );

    $parts = explode(';',$this->_rule);
    $this->_part = array( 'INTERVAL' => 1 );
    foreach( $parts AS $k => $v ) {
      list( $type, $value ) = explode( '=', $v, 2);
//      dbg_error_log( "RRule", " Parts of %s explode into %s and %s", $v, $type, $value );
      $this->_part[$type] = $value;
    }

    // A little bit of validation
    if ( !isset($this->_part['FREQ']) ) {
    //  dbg_error_log( "ERROR", " RRULE MUST have FREQ=value (%s)", $rrule );
    }
    if ( isset($this->_part['COUNT']) && isset($this->_part['UNTIL'])  ) {
    //  dbg_error_log( "ERROR", " RRULE MUST NOT have both COUNT=value and UNTIL=value (%s)", $rrule );
    }
    if ( isset($this->_part['COUNT']) && intval($this->_part['COUNT']) < 1 ) {
    //  dbg_error_log( "ERROR", " RRULE MUST NOT have both COUNT=value and UNTIL=value (%s)", $rrule );
    }
    if ( !preg_match( '/(YEAR|MONTH|WEEK|DAI)LY/', $this->_part['FREQ']) ) {
    //  dbg_error_log( "ERROR", " RRULE Only FREQ=(YEARLY|MONTHLY|WEEKLY|DAILY) are supported at present (%s)", $rrule );
    }
    if ( $this->_part['FREQ'] == "YEARLY" ) {
      $this->_part['INTERVAL'] *= 12;
      $this->_part['FREQ'] = "MONTHLY";
    }
  }


  /**
  * Processes the array of $relative_days to $base and removes any
  * which are not within the scope of our rule.
  */
  function WithinScope( $base, $relative_days ) {

    $ok_days = array();

    $ptr = $this->_current;

//    dbg_error_log( "RRule", " WithinScope: Processing list of %d days relative to %s", count($relative_days), $base->Render() );
    foreach( $relative_days AS $day => $v ) {

      $test = new iCalDate($base);
      $days_in_month = $test->DaysInMonth();

//      dbg_error_log( "RRule", " WithinScope: Testing for day %d based on %s, with %d days in month", $day, $test->Render(), $days_in_month );
      if ( $day > $days_in_month ) {
        $test->SetMonthDay($days_in_month);
        $test->AddDays(1);
        $day -= $days_in_month;
        $test->SetMonthDay($day);
      }
      else if ( $day < 1 ) {
        $test->SetMonthDay(1);
        $test->AddDays(-1);
        $days_in_month = $test->DaysInMonth();
        $day += $days_in_month;
        $test->SetMonthDay($day);
      }
      else {
        $test->SetMonthDay($day);
      }

//      dbg_error_log( "RRule", " WithinScope: Testing if %s is within scope", count($relative_days), $test->Render() );

      if ( isset($this->_part['UNTIL']) && $test->GreaterThan($this->_part['UNTIL']) ) {
        $this->_finished = true;
        return $ok_days;
      }

      // if ( $this->_current >= 0 && $test->LessThan($this->_dates[$this->_current]) ) continue;

      if ( !$test->LessThan($this->_first) ) {
//        dbg_error_log( "RRule", " WithinScope: Looks like %s is within scope", $test->Render() );
        $ok_days[$day] = $test;
        $ptr++;
      }

      if ( isset($this->_part['COUNT']) && $ptr >= $this->_part['COUNT'] ) {
        $this->_finished = true;
        return $ok_days;
      }

    }

    return $ok_days;
  }


  /**
  * This is most of the meat of the RRULE processing, where we find the next date.
  * We maintain an
  */
  function &GetNext( ) {

    if ( $this->_current < 0 ) {
      $next = new iCalDate($this->_first);
      $this->_current++;
    }
    else {
      $next = new iCalDate($this->_dates[$this->_current]);
      $this->_current++;

      /**
      * If we have already found some dates we may just be able to return one of those.
      */
      if ( isset($this->_dates[$this->_current]) ) {
//        dbg_error_log( "RRule", " GetNext: Returning %s, (%d'th)", $this->_dates[$this->_current]->Render(), $this->_current );
        return $this->_dates[$this->_current];
      }
      else {
        if ( isset($this->_part['COUNT']) && $this->_current >= $this->_part['COUNT'] ) // >= since _current is 0-based and COUNT is 1-based
          $this->_finished = true;
      }
    }

    if ( $this->_finished ) {
      $next = null;
      return $next;
    }

    $days = array();
    if ( isset($this->_part['WKST']) ) $next->SetWeekStart($this->_part['WKST']);
    if ( $this->_part['FREQ'] == "MONTHLY" ) {
//      dbg_error_log( "RRule", " GetNext: Calculating more dates for MONTHLY rule" );
      $limit = 200;
      do {
        $limit--;
        do {
          $limit--;
          if ( $this->_started ) {
            $next->AddMonths($this->_part['INTERVAL']);
          }
          else {
            $this->_started = true;
          }
        }
        while ( isset($this->_part['BYMONTH']) && $limit > 0 && ! $next->TestByMonth($this->_part['BYMONTH']) );

        if ( isset($this->_part['BYDAY']) ) {
          $days = $next->GetMonthByDay($this->_part['BYDAY']);
        }
        else if ( isset($this->_part['BYMONTHDAY']) ) {
          $days = $next->GetMonthByMonthDay($this->_part['BYMONTHDAY']);
        }
        else
          $days[$next->_dd] = $next->_dd;

        if ( isset($this->_part['BYSETPOS']) ) {
          $days = $next->ApplyBySetpos($this->_part['BYSETPOS'], $days);
        }

        $days = $this->WithinScope( $next, $days);
      }
      while( $limit && count($days) < 1 && ! $this->_finished );
//      dbg_error_log( "RRule", " GetNext: Found %d days for MONTHLY rule", count($days) );

    }
    else if ( $this->_part['FREQ'] == "WEEKLY" ) {
//      dbg_error_log( "RRule", " GetNext: Calculating more dates for WEEKLY rule" );
      $limit = 200;
      do {
        $limit--;
        if ( $this->_started ) {
          $next->AddDays($this->_part['INTERVAL'] * 7);
        }
        else {
          $this->_started = true;
        }

        if ( isset($this->_part['BYDAY']) ) {
          $days = $next->GetWeekByDay($this->_part['BYDAY'], false );
        }
        else
          $days[$next->_dd] = $next->_dd;

        if ( isset($this->_part['BYSETPOS']) ) {
          $days = $next->ApplyBySetpos($this->_part['BYSETPOS'], $days);
        }

        $days = $this->WithinScope( $next, $days);
      }
      while( $limit && count($days) < 1 && ! $this->_finished );

//      dbg_error_log( "RRule", " GetNext: Found %d days for WEEKLY rule", count($days) );
    }
    else if ( $this->_part['FREQ'] == "DAILY" ) {
//      dbg_error_log( "RRule", " GetNext: Calculating more dates for DAILY rule" );
      $limit = 100;
      do {
        $limit--;
        if ( $this->_started ) {
          $next->AddDays($this->_part['INTERVAL']);
        }

        if ( isset($this->_part['BYDAY']) ) {
          $days = $next->GetWeekByDay($this->_part['BYDAY'], $this->_started );
        }
        else
          $days[$next->_dd] = $next->_dd;

        if ( isset($this->_part['BYSETPOS']) ) {
          $days = $next->ApplyBySetpos($this->_part['BYSETPOS'], $days);
        }

        $days = $this->WithinScope( $next, $days);
        $this->_started = true;
      }
      while( $limit && count($days) < 1 && ! $this->_finished );

//      dbg_error_log( "RRule", " GetNext: Found %d days for DAILY rule", count($days) );
    }

    $ptr = $this->_current;
    foreach( $days AS $k => $v ) {
      $this->_dates[$ptr++] = $v;
    }

    if ( isset($this->_dates[$this->_current]) ) {
//      dbg_error_log( "RRule", " GetNext: Returning %s, (%d'th)", $this->_dates[$this->_current]->Render(), $this->_current );
      return $this->_dates[$this->_current];
    }
    else {
//      dbg_error_log( "RRule", " GetNext: Returning null date" );
      $next = null;
      return $next;
    }
  }

}

