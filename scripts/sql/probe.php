#!/usr/bin/env php
<?php

$root = dirname(dirname(dirname(__FILE__)));
require_once $root.'/scripts/__init_script__.php';

$data = array();

$conn_r = id(new PhabricatorUser())->establishConnection('r');
$databases = queryfx_all($conn_r, 'SHOW DATABASES');
foreach ($databases as $database) {
  $name = head($database);
  queryfx($conn_r, 'USE %C', $name);
  $tables = queryfx_all(
    $conn_r,
    'SHOW TABLE STATUS');
  $tables = ipull($tables, null, 'Name');
  $data[$name] = $tables;
}

$totals = array_fill_keys(array_keys($data), 0);
$overall = 0;

foreach ($data as $db => $tables) {
  foreach ($tables as $table => $info) {
    $table_size = $info['Data_length'] + $info['Index_length'];

    $data[$db][$table]['_totalSize'] = $table_size;
    $totals[$db] += $table_size;
    $overall += $table_size;
  }
}

echo "APPROXIMATE TABLE SIZES\n";
asort($totals);
foreach ($totals as $db => $size) {
  printf("%-32.32s %18s\n", $db, fmt($totals[$db], $overall));
  $data[$db] = isort($data[$db], '_totalSize');
  foreach ($data[$db] as $table => $info) {
    printf("    %-28.28s %18s\n", $table, fmt($info['_totalSize'], $overall));
  }
}
printf("%-32.32s %18s\n", 'TOTAL', fmt($overall, $overall));

function fmt($n, $o) {

  return sprintf(
    '%8.8s MB  %5.5s%%',
    number_format($n / (1024 * 1024), 1),
    sprintf('%3.1f', 100 * ($n / $o)));
}


