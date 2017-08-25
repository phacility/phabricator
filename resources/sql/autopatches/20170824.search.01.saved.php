<?php

// Before T12956, normal users could reorder (and disable) builtin queries.
// After that change, there is a single global order which can only be
// changed by administrators.

// This migration removes the rows which store individual reordering and
// disabling of queries. If a user had reordered queries in such a way that
// a builtin query was at the top of the list, we try to write a preference
// which pins that query as their default to minimize disruption.

$table = new PhabricatorNamedQuery();
$conn = $table->establishConnection('w');

$config_table = new PhabricatorNamedQueryConfig();

foreach (new LiskMigrationIterator($table) as $named_query) {

  // If this isn't a builtin query, it isn't changing. Leave it alone.
  if (!$named_query->getIsBuiltin()) {
    continue;
  }

  // If the user reordered things but left a builtin query at the top, pin
  // the query before we remove the row.
  if ($named_query->getSequence() == 1) {
    queryfx(
      $conn,
      'INSERT IGNORE INTO %T
        (engineClassName, scopePHID, properties, dateCreated, dateModified)
       VALUES
        (%s, %s, %s, %d, %d)',
      $config_table->getTableName(),
      $named_query->getEngineClassName(),
      $named_query->getUserPHID(),
      phutil_json_encode(
        array(
          PhabricatorNamedQueryConfig::PROPERTY_PINNED =>
            $named_query->getQueryKey(),
        )),
      PhabricatorTime::getNow(),
      PhabricatorTime::getNow());
  }

  $named_query->delete();
}
