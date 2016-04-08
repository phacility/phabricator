<?php

$table = new ManiphestTransaction();
$conn_w = $table->establishConnection('w');

foreach (new LiskMigrationIterator($table) as $xaction) {
  $type = $xaction->getTransactionType();
  $id = $xaction->getID();

  // This is an old ManiphestTransaction::TYPE_COLUMN. It did not do anything
  // on its own and was hidden from the UI, so we're just going to remove it.
  if ($type == 'column') {
    queryfx(
      $conn_w,
      'DELETE FROM %T WHERE id = %d',
      $table->getTableName(),
      $id);
    continue;
  }

  // This is an old ManiphestTransaction::TYPE_PROJECT_COLUMN. It moved
  // tasks between board columns; we're going to replace it with a modern
  // PhabricatorTransactions::TYPE_COLUMNS transaction.
  if ($type == 'projectcolumn') {
    try {
      $new = $xaction->getNewValue();
      if (!$new || !is_array($new)) {
        continue;
      }

      $column_phids = idx($new, 'columnPHIDs');
      if (!is_array($column_phids) || !$column_phids) {
        continue;
      }

      $column_phid = head($column_phids);
      if (!$column_phid) {
        continue;
      }

      $board_phid = idx($new, 'projectPHID');
      if (!$board_phid) {
        continue;
      }

      $before_phid = idx($new, 'beforePHID');
      $after_phid = idx($new, 'afterPHID');

      $old = $xaction->getOldValue();
      if ($old && is_array($old)) {
        $from_phids = idx($old, 'columnPHIDs');
        $from_phids = array_values($from_phids);
      } else {
        $from_phids = array();
      }

      $replacement = array(
        'columnPHID' => $column_phid,
        'boardPHID' => $board_phid,
        'fromColumnPHIDs' => $from_phids,
      );

      if ($before_phid) {
        $replacement['beforePHID'] = $before_phid;
      } else if ($after_phid) {
        $replacement['afterPHID'] = $after_phid;
      }

      queryfx(
        $conn_w,
        'UPDATE %T SET transactionType = %s, oldValue = %s, newValue = %s
          WHERE id = %d',
        $table->getTableName(),
        PhabricatorTransactions::TYPE_COLUMNS,
        'null',
        phutil_json_encode(array($replacement)),
        $id);
    } catch (Exception $ex) {
      // If anything went awry, just move on.
    }
  }


}
