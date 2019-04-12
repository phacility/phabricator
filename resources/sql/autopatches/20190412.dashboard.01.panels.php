<?php

// Convert dashboards to a new storage format. The old storage format looks
// like this:

// {
//   "0": ["PHID-DSHP-A", ...],
//   "1": ["PHID-DSHP-B", ...]
// }

// The new storage format looks like this:

// [
//   {
//     "panelKey": "abcdefgh",
//     "panelPHID": "PHID-DSHP-A",
//     "columnKey": "left"
//   },
//   ...
// ]

// One major issue with the old storage format is that when multiple copies of
// a single dashboard panel appeared on the same dashboard, the UI had a lot
// of difficulty acting on a particular copy because copies were identified
// only by PHID and all copies of the same panel have the same panel PHID.

$dashboard_table = new PhabricatorDashboard();
$conn = $dashboard_table->establishConnection('r');
$table_name = $dashboard_table->getTableName();

$rows = new LiskRawMigrationIterator($conn, $table_name);
foreach ($rows as $row) {
  $config = $row['layoutConfig'];

  try {
    $config = phutil_json_decode($config);
  } catch (Exception $ex) {
    $config = array();
  }

  if (!is_array($config)) {
    $config = array();
  }

  $panels = idx($config, 'panelLocations');
  if (!is_array($panels)) {
    $panels = array();
  }

  if (idx($config, 'layoutMode') === 'layout-mode-full') {
    $column_map = array(
      0 => 'main',
    );
  } else {
    $column_map = array(
      0 => 'left',
      1 => 'right',
    );
  }

  $panel_list = array();
  foreach ($panels as $column_idx => $panel_phids) {
    $column_key = idx($column_map, $column_idx, 'unknown');
    foreach ($panel_phids as $panel_phid) {
      $panel_list[] = array(
        'panelKey' => Filesystem::readRandomCharacters(8),
        'columnKey' => $column_key,
        'panelPHID' => $panel_phid,
      );
    }
  }
  unset($config['panelLocations']);
  $config['panels'] = $panel_list;

  queryfx(
    $conn,
    'UPDATE %R SET layoutConfig = %s WHERE id = %d',
    $dashboard_table,
    phutil_json_encode($config),
    $row['id']);
}
