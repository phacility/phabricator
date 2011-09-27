<?php

/*
 * Copyright 2011 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

function phabricator_date($epoch, $user) {
  return __phabricator_format_local_time(
    $epoch,
    $user,
    'M j Y');
}

function phabricator_time($epoch, $user) {
  return __phabricator_format_local_time(
    $epoch,
    $user,
    'g:i A');
}

function phabricator_datetime($epoch, $user) {
  return __phabricator_format_local_time(
    $epoch,
    $user,
    'M j Y, g:i A');
}

/**
 * Internal date rendering method. Do not call this directly; instead, call
 * @{function:phabricator_date}, @{function:phabricator_time}, or
 * @{function:phabricator_datetime}.
 *
 * @param int Unix epoch timestamp.
 * @param PhabricatorUser User viewing the timestamp.
 * @param string Date format, as per DateTime class.
 * @return string Formatted, local date/time.
 */
function __phabricator_format_local_time($epoch, $user, $format) {
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
  $date = new DateTime('@'.$epoch);
  $date->setTimeZone($zone);

  return $date->format($format);
}

function phabricator_format_relative_time($duration) {
  return phabricator_format_units_generic(
    $duration,
    array(60, 60, 24, 7),
    array('s', 'm', 'h', 'd', 'w'),
    $precision = 0);
}

function phabricator_format_timestamp($epoch) {
  $difference = (time() - $epoch);

  if ($difference < 0) {
    $difference = -$difference;
    $relative = 'from now';
  } else {
    $relative = 'ago';
  }

  if ($difference < 60 * 60 * 24) {
    return phabricator_format_relative_time($difference).' '.$relative;
  } else if (date('Y') == date('Y', $epoch)) {
    return date('M j, g:i A', $epoch);
  } else {
    return date('F jS, Y', $epoch);
  }
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
  while ($n > $scale && count($labels)) {
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

