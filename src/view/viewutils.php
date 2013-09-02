<?php

function phabricator_date($epoch, PhabricatorUser $user) {
  return phabricator_format_local_time(
    $epoch,
    $user,
    _phabricator_date_format($epoch));
}

function phabricator_on_relative_date($epoch, $user) {
  return phabricator_relative_date($epoch, $user, true);
}

function phabricator_relative_date($epoch, $user, $on = false) {
  static $today;
  static $yesterday;

  if (!$today || !$yesterday) {
    $now = time();
    $today = phabricator_date($now, $user);
    $yesterday = phabricator_date($now - 86400, $user);
  }

  $date = phabricator_date($epoch, $user);

  if ($date === $today) {
    return 'today';
  }

  if ($date === $yesterday) {
    return 'yesterday';
  }

  return (($on ? 'on ' : '').$date);
}

function phabricator_time($epoch, $user) {
  return phabricator_format_local_time(
    $epoch,
    $user,
    _phabricator_time_format($user));
}

function phabricator_datetime($epoch, $user) {
  return phabricator_format_local_time(
    $epoch,
    $user,
    pht('%s, %s',
      _phabricator_date_format($epoch),
      _phabricator_time_format($user)));
}

function _phabricator_date_format($epoch) {
  $now = time();
  $shift = 30 * 24 * 60 * 60;
  if ($epoch < $now + $shift && $epoch > $now - $shift) {
    $format = pht('D, M j');
  } else {
    $format = pht('M j Y');
  }
  return $format;
}

function _phabricator_time_format($user) {
  $prefs = $user->loadPreferences();

  $pref = $prefs->getPreference(
    PhabricatorUserPreferences::PREFERENCE_TIME_FORMAT);

  if (strlen($pref)) {
    return $pref;
  }

  return pht('g:i A');
}

/**
 * This function does not usually need to be called directly. Instead, call
 * @{function:phabricator_date}, @{function:phabricator_time}, or
 * @{function:phabricator_datetime}.
 *
 * @param int Unix epoch timestamp.
 * @param PhabricatorUser User viewing the timestamp.
 * @param string Date format, as per DateTime class.
 * @return string Formatted, local date/time.
 */
function phabricator_format_local_time($epoch, $user, $format) {
  if (!$epoch) {
    // If we're missing date information for something, the DateTime class will
    // throw an exception when we try to construct an object. Since this is a
    // display function, just return an empty string.
    return '';
  }

  $user_zone = $user->getTimezoneIdentifier();

  static $zones = array();
  if (empty($zones[$user_zone])) {
    $zones[$user_zone] = new DateTimeZone($user_zone);
  }
  $zone = $zones[$user_zone];

  // NOTE: Although DateTime takes a second DateTimeZone parameter to its
  // constructor, it ignores it if the date string includes timezone
  // information. Further, it treats epoch timestamps ("@946684800") as having
  // a UTC timezone. Set the timezone explicitly after constructing the object.
  try {
    $date = new DateTime('@'.$epoch);
  } catch (Exception $ex) {
    // NOTE: DateTime throws an empty exception if the format is invalid,
    // just replace it with a useful one.
    throw new Exception(
      pht("Construction of a DateTime() with epoch '%s' ".
      "raised an exception.", $epoch));
  }

  $date->setTimeZone($zone);

  return PhutilTranslator::getInstance()->translateDate($format, $date);
}

function phabricator_format_relative_time($duration) {
  return phabricator_format_units_generic(
    $duration,
    array(60, 60, 24, 7),
    array('s', 'm', 'h', 'd', 'w'),
    $precision = 0);
}

/**
 * Format a relative time (duration) into weeks, days, hours, minutes,
 * seconds, but unlike phabricator_format_relative_time, does so for more than
 * just the largest unit.
 *
 * @param int Duration in seconds.
 * @param int Levels to render - will render the three highest levels, ie:
 *            5 h, 37 m, 1 s
 * @return string Human-readable description.
 */
function phabricator_format_relative_time_detailed($duration, $levels = 2) {
  if ($duration == 0) {
    return 'now';
  }
  $levels = max(1, min($levels, 5));
  $remainder = 0;

  $is_negative = false;
  if ($duration < 0) {
    $is_negative = true;
    $duration = abs($duration);
  }

  $this_level = 1;
  $detailed_relative_time = phabricator_format_units_generic(
    $duration,
    array(60, 60, 24, 7),
    array('s', 'm', 'h', 'd', 'w'),
    $precision = 0,
    $remainder);
  $duration = $remainder;

  while ($remainder > 0 && $this_level < $levels) {
    $detailed_relative_time .= ', '.phabricator_format_units_generic(
      $duration,
      array(60, 60, 24, 7),
      array('s', 'm', 'h', 'd', 'w'),
      $precision = 0,
      $remainder);
    $duration = $remainder;
    $this_level++;
  };

  if ($is_negative) {
    $detailed_relative_time .= ' ago';
  }

  return $detailed_relative_time;
}

/**
 * Format a byte count for human consumption, e.g. "10MB" instead of
 * "10000000".
 *
 * @param int Number of bytes.
 * @return string Human-readable description.
 */
function phabricator_format_bytes($bytes) {
  return phabricator_format_units_generic(
    $bytes,
    // NOTE: Using the SI version of these units rather than the 1024 version.
    array(1000, 1000, 1000, 1000, 1000),
    array('B', 'KB', 'MB', 'GB', 'TB', 'PB'),
    $precision = 0);
}


/**
 * Parse a human-readable byte description (like "6MB") into an integer.
 *
 * @param string  Human-readable description.
 * @return int    Number of represented bytes.
 */
function phabricator_parse_bytes($input) {
  $bytes = trim($input);
  if (!strlen($bytes)) {
    return null;
  }

  // NOTE: Assumes US-centric numeral notation.
  $bytes = preg_replace('/[ ,]/', '', $bytes);

  $matches = null;
  if (!preg_match('/^(?:\d+(?:[.]\d+)?)([kmgtp]?)b?$/i', $bytes, $matches)) {
    throw new Exception("Unable to parse byte size '{$input}'!");
  }

  $scale = array(
    'k' => 1000,
    'm' => 1000 * 1000,
    'g' => 1000 * 1000 * 1000,
    't' => 1000 * 1000 * 1000 * 1000,
    'p' => 1000 * 1000 * 1000 * 1000 * 1000,
  );

  $bytes = (float)$bytes;
  if ($matches[1]) {
    $bytes *= $scale[strtolower($matches[1])];
  }

  return (int)$bytes;
}


function phabricator_format_units_generic(
  $n,
  array $scales,
  array $labels,
  $precision  = 0,
  &$remainder = null) {

  $is_negative = false;
  if ($n < 0) {
    $is_negative = true;
    $n = abs($n);
  }

  $remainder = 0;
  $accum = 1;

  $scale = array_shift($scales);
  $label = array_shift($labels);
  while ($n >= $scale && count($labels)) {
    $remainder += ($n % $scale) * $accum;
    $n /= $scale;
    $accum *= $scale;
    $label = array_shift($labels);
    if (!count($scales)) {
      break;
    }
    $scale = array_shift($scales);
  }

  if ($is_negative) {
    $n = -$n;
    $remainder = -$remainder;
  }

  if ($precision) {
    $num_string = number_format($n, $precision);
  } else {
    $num_string = (int)floor($n);
  }

  if ($label) {
    $num_string .= ' '.$label;
  }

  return $num_string;
}

