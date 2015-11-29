<?php
/**
* Class for parsing RRule and getting us the dates
*/

$ical_weekdays = array( 'SU' => 0, 'MO' => 1, 'TU' => 2, 'WE' => 3, 'TH' => 4, 'FR' => 5, 'SA' => 6 );


/**
* A Class for handling dates in iCalendar format.  We do make the simplifying assumption
* that all date handling in here is normalised to GMT.  One day we might provide some
* functions to do that, but for now it is done externally.
*
*/
class iCalDate {

  /** Text version */
  var $_text;

  /** Epoch version */
  var $_epoch;

  /** Fragmented parts */
  var $_yy;
  var $_mo;
  var $_dd;
  var $_hh;
  var $_mi;
  var $_ss;
  var $_tz;

  /** Which day of the week does the week start on */
  var $_wkst;

  /**#@-*/

  /**
  * The constructor takes either an iCalendar date, a text string formatted as
  * an iCalendar date, or epoch seconds.
  */
  function iCalDate( $input ) {
    if ( gettype($input) == 'object' ) {
      $this->_text = $input->_text;
      $this->_epoch = $input->_epoch;
      $this->_yy = $input->_yy;
      $this->_mo = $input->_mo;
      $this->_dd = $input->_dd;
      $this->_hh = $input->_hh;
      $this->_mi = $input->_mi;
      $this->_ss = $input->_ss;
      $this->_tz = $input->_tz;
      return;
    }

    $this->_wkst = 1; // Monday
    if ( preg_match( '/^\d{8}[T ]\d{6}$/', $input ) ) {
      $this->SetLocalDate($input);
    }
    else if ( preg_match( '/^\d{8}[T ]\d{6}Z$/', $input ) ) {
      $this->SetGMTDate($input);
    }
    else if ( intval($input) == 0 ) {
      $this->SetLocalDate(strtotime($input));
      return;
    }
    else {
      $this->SetEpochDate($input);
    }
  }


  /**
  * Set the date from a text string
  */
  function SetGMTDate( $input ) {
    $this->_text = $input;
    $this->_PartsFromText();
    $this->_GMTEpochFromParts();
  }


  /**
  * Set the date from a text string
  */
  function SetLocalDate( $input ) {
    $this->_text = $input;
    $this->_PartsFromText();
    $this->_EpochFromParts();
  }


  /**
  * Set the date from an epoch
  */
  function SetEpochDate( $input ) {
    $this->_epoch = intval($input);
    $this->_TextFromEpoch();
    $this->_PartsFromText();
  }


  /**
  * Given an epoch date, convert it to text
  */
  function _TextFromEpoch() {
    $this->_text = date('Ymd\THis', $this->_epoch );
//    dbg_error_log( "RRule", " Text %s from epoch %d", $this->_text, $this->_epoch );
  }

  /**
  * Given a GMT epoch date, convert it to text
  */
  function _GMTTextFromEpoch() {
    $this->_text = gmdate('Ymd\THis', $this->_epoch );
//    dbg_error_log( "RRule", " Text %s from epoch %d", $this->_text, $this->_epoch );
  }

  /**
  * Given a text date, convert it to parts
  */
  function _PartsFromText() {
    $this->_yy = intval(substr($this->_text,0,4));
    $this->_mo = intval(substr($this->_text,4,2));
    $this->_dd = intval(substr($this->_text,6,2));
    $this->_hh = intval(substr($this->_text,9,2));
    $this->_mi = intval(substr($this->_text,11,2));
    $this->_ss = intval(substr($this->_text,13,2));
  }


  /**
  * Given a GMT text date, convert it to an epoch
  */
  function _GMTEpochFromParts() {
    $this->_epoch = gmmktime ( $this->_hh, $this->_mi, $this->_ss, $this->_mo, $this->_dd, $this->_yy );
//    dbg_error_log( "RRule", " Epoch %d from %04d-%02d-%02d %02d:%02d:%02d", $this->_epoch, $this->_yy, $this->_mo, $this->_dd, $this->_hh, $this->_mi, $this->_ss );
  }


  /**
  * Given a local text date, convert it to an epoch
  */
  function _EpochFromParts() {
    $this->_epoch = mktime ( $this->_hh, $this->_mi, $this->_ss, $this->_mo, $this->_dd, $this->_yy );
//    dbg_error_log( "RRule", " Epoch %d from %04d-%02d-%02d %02d:%02d:%02d", $this->_epoch, $this->_yy, $this->_mo, $this->_dd, $this->_hh, $this->_mi, $this->_ss );
  }


  /**
  * Set the day of week used for calculation of week starts
  *
  * @param string $weekstart The day of the week which is the first business day.
  */
  function SetWeekStart($weekstart) {
    global $ical_weekdays;
    $this->_wkst = $ical_weekdays[$weekstart];
  }

  /**
   * Retuns unix timestamp
   * @return int
   */
  function getTimestamp () {
    return $this->_epoch;
  }

  /**
  * Set the day of week used for calculation of week starts
  */
  function Render( $fmt = 'Y-m-d H:i:s' ) {
    return date( $fmt, $this->_epoch );
  }


  /**
  * Render the date as GMT
  */
  function RenderGMT( $fmt = 'Ymd\THis\Z' ) {
    return gmdate( $fmt, $this->_epoch );
  }

  /** 
  * return which day in week it has
  */

  function Weekday ( $fmt = 0, $lang = "cz" )
  {
    if ( ! in_array($lang, [ "cz", "en" ] ) )
      return "N/A";

    $cz_weekdays = array ( 1 => array ( "Pondělí", "Po" ),
                           2 => array ( "Úterý", "Út" ),
                           3 => array ( "Středa", "St" ),
                           4 => array ( "Čtvrtek", "Čt" ),
                           5 => array ( "Pátek", "Pá" ),
                           6 => array ( "Sobota", "So" ),
                           0 => array ( "Neděle", "Ne" ) );
    $en_weekdays = array ( 1 => array ( "Monday", "Mon", "Mo" ),
                           2 => array ( "Tuesday", "Tue", "Tu" ),
                           3 => array ( "Wednesday", "Wen", "We" ),
                           4 => array ( "Thursday", "Thr", "Th" ),
                           5 => array ( "Friday", "Fri", "Fri" ),
                           6 => array ( "Saturday", "Sat", "Sa" ),
                           0 => array ( "Sunday", "Sun", "Su" ) );
    $weekdays = array ( "cz" => $cz_weekdays, "en" => $en_weekdays );

    if(! isset($weekdays [ $lang ] [ date ( 'w', $this->_epoch ) ][$fmt] ) )
      return "N/A";

    return $weekdays [ $lang ] [ date ( 'w', $this->_epoch ) ] [ $fmt ];
  }

  function Week ()
  {
    return date ( "W", $this->_epoch );
  }


  /**
  * No of days in a month 1(Jan) - 12(Dec)
  */
  function DaysInMonth( $mo=false, $yy=false ) {
    if ( $mo === false ) $mo = $this->_mo;
    switch( $mo ) {
      case  1: // January
      case  3: // March
      case  5: // May
      case  7: // July
      case  8: // August
      case 10: // October
      case 12: // December
        return 31;
        break;

      case  4: // April
      case  6: // June
      case  9: // September
      case 11: // November
        return 30;
        break;

      case  2: // February
        if ( $yy === false ) $yy = $this->_yy;
        if ( (($yy % 4) == 0) && ((($yy % 100) != 0) || (($yy % 400) == 0) ) ) return 29;
        return 28;
        break;

      default:
        dbg_error_log( "ERROR"," Invalid month of '%s' passed to DaysInMonth", $mo );
        break;

    }
  }


  /**
  * Set the day in the month to what we have been given
  */
  function SetMonthDay( $dd ) {
    if ( $dd == $this->_dd ) return; // Shortcut
    $dd = min($dd,$this->DaysInMonth());
    $this->_dd = $dd;
    $this->_EpochFromParts();
    $this->_TextFromEpoch();
  }


  /**
  * Add some number of months to a date
  */
  function AddMonths( $mo ) {
//    dbg_error_log( "RRule", " Adding %d months to %s", $mo, $this->_text );
    $this->_mo += $mo;
    while ( $this->_mo < 1 ) {
      $this->_mo += 12;
      $this->_yy--;
    }
    while ( $this->_mo > 12 ) {
      $this->_mo -= 12;
      $this->_yy++;
    }

    if ( ($this->_dd > 28 && $this->_mo == 2) || $this->_dd > 30 ) {
      // Ensure the day of month is still reasonable and coerce to last day of month if needed
      $dim = $this->DaysInMonth();
      if ( $this->_dd > $dim ) {
        $this->_dd = $dim;
      }
    }
    $this->_EpochFromParts();
    $this->_TextFromEpoch();
//    dbg_error_log( "RRule", " Added %d months and got %s", $mo, $this->_text );
  }


  /**
  * Add some integer number of days to a date
  */
  function AddDays( $dd ) {
    $at_start = $this->_text;
    $this->_dd += $dd;
    while ( 1 > $this->_dd ) {
      $this->_mo--;
      if ( $this->_mo < 1 ) {
        $this->_mo += 12;
        $this->_yy--;
      }
      $this->_dd += $this->DaysInMonth();
    }
    while ( ($dim = $this->DaysInMonth($this->_mo)) < $this->_dd ) {
      $this->_dd -= $dim;
      $this->_mo++;
      if ( $this->_mo > 12 ) {
        $this->_mo -= 12;
        $this->_yy++;
      }
    }
    $this->_EpochFromParts();
    $this->_TextFromEpoch();
//    dbg_error_log( "RRule", " Added %d days to %s and got %s", $dd, $at_start, $this->_text );
  }


  /**
  * Add duration
  */
  function AddDuration( $duration ) {
    if ( strstr($duration,'T') === false ) $duration .= 'T';
    list( $sign, $days, $time ) = preg_split( '/[PT]/', $duration );
    $sign = ( $sign == "-" ? -1 : 1);
//    dbg_error_log( "RRule", " Adding duration to '%s' of sign: %d,  days: %s,  time: %s", $this->_text, $sign, $days, $time );
    if ( preg_match( '/(\d+)(D|W)/', $days, $matches ) ) {
      $days = intval($matches[1]);
      if ( $matches[2] == 'W' ) $days *= 7;
      $this->AddDays( $days * $sign );
    }
    $hh = 0;    $mi = 0;    $ss = 0;
    if ( preg_match( '/(\d+)(H)/', $time, $matches ) )  $hh = $matches[1];
    if ( preg_match( '/(\d+)(M)/', $time, $matches ) )  $mi = $matches[1];
    if ( preg_match( '/(\d+)(S)/', $time, $matches ) )  $ss = $matches[1];

//    dbg_error_log( "RRule", " Adding %02d:%02d:%02d * %d to %02d:%02d:%02d", $hh, $mi, $ss, $sign, $this->_hh, $this->_mi, $this->_ss );
    $this->_hh += ($hh * $sign);
    $this->_mi += ($mi * $sign);
    $this->_ss += ($ss * $sign);

    if ( $this->_ss < 0 ) {  $this->_mi -= (intval(abs($this->_ss/60))+1); $this->_ss += ((intval(abs($this->_mi/60))+1) * 60); }
    if ( $this->_ss > 59) {  $this->_mi += (intval(abs($this->_ss/60))+1); $this->_ss -= ((intval(abs($this->_mi/60))+1) * 60); }
    if ( $this->_mi < 0 ) {  $this->_hh -= (intval(abs($this->_mi/60))+1); $this->_mi += ((intval(abs($this->_mi/60))+1) * 60); }
    if ( $this->_mi > 59) {  $this->_hh += (intval(abs($this->_mi/60))+1); $this->_mi -= ((intval(abs($this->_mi/60))+1) * 60); }
    if ( $this->_hh < 0 ) {  $this->AddDays( -1 * (intval(abs($this->_hh/24))+1) );  $this->_hh += ((intval(abs($this->_hh/24))+1)*24);  }
    if ( $this->_hh > 23) {  $this->AddDays( (intval(abs($this->_hh/24))+1) );       $this->_hh -= ((intval(abs($this->_hh/24))+1)*24);  }

    $this->_EpochFromParts();
    $this->_TextFromEpoch();
  }


  /**
  * Produce an iCalendar format DURATION for the difference between this an another iCalDate
  *
  * @param date $from The start of the period
  * @return string The date difference, as an iCalendar duration format
  */
  function DateDifference( $from ) {
    if ( !is_object($from) ) {
      $from = new iCalDate($from);
    }
    if ( $from->_epoch < $this->_epoch ) {
      /** One way to simplify is to always go for positive differences */
      return( "-". $from->DateDifference( $self ) );
    }
//    if ( $from->_yy == $this->_yy && $from->_mo == $this->_mo ) {
      /** Also somewhat simpler if we can use seconds */
      $diff = $from->_epoch - $this->_epoch;
      $result = "";
      if ( $diff >= 86400) {
        $result = intval($diff / 86400);
        $diff = $diff % 86400;
        if ( $diff == 0 && (($result % 7) == 0) ) {
          // Duration is an integer number of weeks.
          $result .= intval($result / 7) . "W";
          return $result;
        }
        $result .= "D";
      }
      $result = "P".$result."T";
      if ( $diff >= 3600) {
        $result .= intval($diff / 3600) . "H";
        $diff = $diff % 3600;
      }
      if ( $diff >= 60) {
        $result .= intval($diff / 60) . "M";
        $diff = $diff % 60;
      }
      if ( $diff > 0) {
        $result .= intval($diff) . "S";
      }
      return $result;
//    }

/**
* From an intense reading of RFC2445 it appears that durations which are not expressible
* in Weeks/Days/Hours/Minutes/Seconds are invalid.
*  ==> This code is not needed then :-)
    $yy = $from->_yy - $this->_yy;
    $mo = $from->_mo - $this->_mo;
    $dd = $from->_dd - $this->_dd;
    $hh = $from->_hh - $this->_hh;
    $mi = $from->_mi - $this->_mi;
    $ss = $from->_ss - $this->_ss;

    if ( $ss < 0 ) {  $mi -= 1;   $ss += 60;  }
    if ( $mi < 0 ) {  $hh -= 1;   $mi += 60;  }
    if ( $hh < 0 ) {  $dd -= 1;   $hh += 24;  }
    if ( $dd < 0 ) {  $mo -= 1;   $dd += $this->DaysInMonth();  } // Which will use $this->_(mo|yy) - seemingly sensible
    if ( $mo < 0 ) {  $yy -= 1;   $mo += 12;  }

    $result = "";
    if ( $yy > 0) {    $result .= $yy."Y";   }
    if ( $mo > 0) {    $result .= $mo."M";   }
    if ( $dd > 0) {    $result .= $dd."D";   }
    $result .= "T";
    if ( $hh > 0) {    $result .= $hh."H";   }
    if ( $mi > 0) {    $result .= $mi."M";   }
    if ( $ss > 0) {    $result .= $ss."S";   }
    return $result;
*/
  }

  /**
  * Test to see if our _mo matches something in the list of months we have received.
  * @param string $monthlist A comma-separated list of months.
  * @return boolean Whether this date falls within one of those months.
  */
  function TestByMonth( $monthlist ) {
//    dbg_error_log( "RRule", " Testing BYMONTH %s against month %d", (isset($monthlist) ? $monthlist : "no month list"), $this->_mo );
    if ( !isset($monthlist) ) return true;  // If BYMONTH is not specified any month is OK
    $months = array_flip(explode( ',',$monthlist ));
    return isset($months[$this->_mo]);
  }

  /**
  * Applies any BYDAY to the month to return a set of days
  * @param string $byday The BYDAY rule
  * @return array An array of the day numbers for the month which meet the rule.
  */
  function GetMonthByDay($byday) {
//    dbg_error_log( "RRule", " Applying BYDAY %s to month", $byday );
    $days_in_month = $this->DaysInMonth();
    $dayrules = explode(',',$byday);
    $set = array();
    $first_dow = (date('w',$this->_epoch) - $this->_dd + 36) % 7;
    foreach( $dayrules AS $k => $v ) {
      $days = $this->MonthDays($first_dow,$days_in_month,$v);
      foreach( $days AS $k2 => $v2 ) {
        $set[$v2] = $v2;
      }
    }
    asort( $set, SORT_NUMERIC );
    return $set;
  }

  /**
  * Applies any BYMONTHDAY to the month to return a set of days
  * @param string $bymonthday The BYMONTHDAY rule
  * @return array An array of the day numbers for the month which meet the rule.
  */
  function GetMonthByMonthDay($bymonthday) {
//    dbg_error_log( "RRule", " Applying BYMONTHDAY %s to month", $bymonthday );
    $days_in_month = $this->DaysInMonth();
    $dayrules = explode(',',$bymonthday);
    $set = array();
    foreach( $dayrules AS $k => $v ) {
      $v = intval($v);
      if ( $v > 0 && $v <= $days_in_month ) $set[$v] = $v;
    }
    asort( $set, SORT_NUMERIC );
    return $set;
  }


  /**
  * Applies any BYDAY to the week to return a set of days
  * @param string $byday The BYDAY rule
  * @param string $increasing When we are moving by months, we want any day of the week, but when by day we only want to increase. Default false.
  * @return array An array of the day numbers for the week which meet the rule.
  */
  function GetWeekByDay($byday, $increasing = false) {
    global $ical_weekdays;
//    dbg_error_log( "RRule", " Applying BYDAY %s to week", $byday );
    $days = explode(',',$byday);
    $dow = date('w',$this->_epoch);
    $set = array();
    foreach( $days AS $k => $v ) {
      $daynum = $ical_weekdays[$v];
      $dd = $this->_dd - $dow + $daynum;
      if ( $daynum < $this->_wkst ) $dd += 7;
      if ( $dd > $this->_dd || !$increasing ) $set[$dd] = $dd;
    }
    asort( $set, SORT_NUMERIC );

    return $set;
  }


  /**
  * Test if $this is greater than the date parameter
  * @param string $lesser The other date, as a local time string
  * @return boolean True if $this > $lesser
  */
  function GreaterThan($lesser) {
    if ( is_object($lesser) ) {
//      dbg_error_log( "RRule", " Comparing %s with %s", $this->_text, $lesser->_text );
      return ( $this->_text > $lesser->_text );
    }
//    dbg_error_log( "RRule", " Comparing %s with %s", $this->_text, $lesser );
    return ( $this->_text > $lesser );  // These sorts of dates are designed that way...
  }


  /**
  * Test if $this is less than the date parameter
  * @param string $greater The other date, as a local time string
  * @return boolean True if $this < $greater
  */
  function LessThan($greater) {
    if ( is_object($greater) ) {
//      dbg_error_log( "RRule", " Comparing %s with %s", $this->_text, $greater->_text );
      return ( $this->_text < $greater->_text );
    }
//    dbg_error_log( "RRule", " Comparing %s with %s", $this->_text, $greater );
    return ( $this->_text < $greater );  // These sorts of dates are designed that way...
  }


  /**
  * Given a MonthDays string like "1MO", "-2WE" return an integer day of the month.
  *
  * @param string $dow_first The day of week of the first of the month.
  * @param string $days_in_month The number of days in the month.
  * @param string $dayspec The specification for a month day (or days) which we parse.
  *
  * @return array An array of the day numbers for the month which meet the rule.
  */
  function &MonthDays($dow_first, $days_in_month, $dayspec) {
    global $ical_weekdays;
//    dbg_error_log( "RRule", "MonthDays: Getting days for '%s'. %d days starting on a %d", $dayspec, $days_in_month, $dow_first );
    $set = array();
    preg_match( '/([0-9-]*)(MO|TU|WE|TH|FR|SA|SU)/', $dayspec, $matches);
    $numeric = intval($matches[1]);
    $dow = $ical_weekdays[$matches[2]];

    $first_matching_day = 1 + ($dow - $dow_first);
    while ( $first_matching_day < 1 ) $first_matching_day += 7;

//    dbg_error_log( "RRule", " MonthDays: Looking at %d for first match on (%s/%s), %d for numeric", $first_matching_day, $matches[1], $matches[2], $numeric );

    while( $first_matching_day <= $days_in_month ) {
      $set[] = $first_matching_day;
      $first_matching_day += 7;
    }

    if ( $numeric != 0 ) {
      if ( $numeric < 0 ) {
        $numeric += count($set);
      }
      else {
        $numeric--;
      }
      $answer = $set[$numeric];
      $set = array( $answer => $answer );
    }
    else {
      $answers = $set;
      $set = array();
      foreach( $answers AS $k => $v ) {
        $set[$v] = $v;
      }
    }

//    dbg_log_array( "RRule", 'MonthDays', $set, false );

    return $set;
  }


  /**
  * Given set position descriptions like '1', '3', '11', '-3' or '-1' and a set,
  * return the subset matching the list of set positions.
  *
  * @param string $bysplist  The list of set positions.
  * @param string $set The set of days that we will apply the positions to.
  *
  * @return array The subset which matches.
  */
  function &ApplyBySetPos($bysplist, $set) {
//    dbg_error_log( "RRule", " ApplyBySetPos: Applying set position '%s' to set of %d days", $bysplist, count($set) );
    $subset = array();
    sort( $set, SORT_NUMERIC );
    $max = count($set);
    $positions = explode( '[^0-9-]', $bysplist );
    foreach( $positions AS $k => $v ) {
      if ( $v < 0 ) {
        $v += $max;
      }
      else {
        $v--;
      }
      $subset[$set[$v]] = $set[$v];
    }
    return $subset;
  }
}