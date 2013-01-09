#!/usr/bin/env php
<?php

$root = dirname(dirname(dirname(__FILE__)));
require_once $root.'/scripts/__init_script__.php';

// http://www.opm.gov/operating_status_schedules/fedhol/
$holidays = array(
  '2013-01-01' => "New Year's Day",
  '2013-01-21' => "Birthday of Martin Luther King, Jr.",
  '2013-02-18' => "Washington's Birthday",
  '2013-05-27' => "Memorial Day",
  '2013-07-04' => "Independence Day",
  '2013-09-02' => "Labor Day",
  '2013-10-14' => "Columbus Day",
  '2013-11-11' => "Veterans Day",
  '2013-11-28' => "Thanksgiving Day",
  '2013-12-25' => "Christmas Day",
  '2014-01-01' => "New Year's Day",
  '2014-01-20' => "Birthday of Martin Luther King, Jr.",
  '2014-02-17' => "Washington's Birthday",
  '2014-05-26' => "Memorial Day",
  '2014-07-04' => "Independence Day",
  '2014-09-01' => "Labor Day",
  '2014-10-13' => "Columbus Day",
  '2014-11-11' => "Veterans Day",
  '2014-11-27' => "Thanksgiving Day",
  '2014-12-25' => "Christmas Day",
  '2015-01-01' => "New Year's Day",
  '2015-01-19' => "Birthday of Martin Luther King, Jr.",
  '2015-02-16' => "Washington's Birthday",
  '2015-05-25' => "Memorial Day",
  '2015-07-03' => "Independence Day",
  '2015-09-07' => "Labor Day",
  '2015-10-12' => "Columbus Day",
  '2015-11-11' => "Veterans Day",
  '2015-11-26' => "Thanksgiving Day",
  '2015-12-25' => "Christmas Day",
  '2016-01-01' => "New Year's Day",
  '2016-01-18' => "Birthday of Martin Luther King, Jr.",
  '2016-02-15' => "Washington's Birthday",
  '2016-05-30' => "Memorial Day",
  '2016-07-04' => "Independence Day",
  '2016-09-05' => "Labor Day",
  '2016-10-10' => "Columbus Day",
  '2016-11-11' => "Veterans Day",
  '2016-11-24' => "Thanksgiving Day",
  '2016-12-26' => "Christmas Day",
);

$table = new PhabricatorCalendarHoliday();
$conn_w = $table->establishConnection('w');
$table_name = $table->getTableName();

foreach ($holidays as $day => $name) {
  queryfx(
    $conn_w,
    'INSERT IGNORE INTO %T (day, name) VALUES (%s, %s)',
    $table_name,
    $day,
    $name);
}
