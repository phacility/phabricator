#!/usr/bin/env php
<?php

$root = dirname(dirname(dirname(__FILE__)));
require_once $root.'/scripts/init/init-script.php';

$xml = $root.'/externals/cldr/cldr_windows_timezones.xml';
$xml = Filesystem::readFile($xml);
$xml = new SimpleXMLElement($xml);

$result_map = array();

$ignore = array(
  'UTC',
  'UTC-11',
  'UTC-02',
  'UTC-08',
  'UTC-09',
  'UTC+12',
);
$ignore = array_fuse($ignore);

$zones = $xml->windowsZones->mapTimezones->mapZone;
foreach ($zones as $zone) {
  $windows_name = (string)$zone['other'];
  $target_name = (string)$zone['type'];

  // Ignore the offset-based timezones from the CLDR map, since we handle
  // these later.
  if (isset($ignore[$windows_name])) {
    continue;
  }

  // We've already seen this timezone so we don't need to add it to the map
  // again.
  if (isset($result_map[$windows_name])) {
    continue;
  }

  $result_map[$windows_name] = $target_name;
}

asort($result_map);

echo id(new PhutilJSON())
  ->encodeFormatted($result_map);
