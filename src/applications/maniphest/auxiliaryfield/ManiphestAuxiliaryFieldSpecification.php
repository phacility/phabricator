<?php

/**
 * TODO: Destroy after ApplicationTransactions.
 */
final class ManiphestAuxiliaryFieldSpecification {

  public static function writeLegacyAuxiliaryUpdates(
    ManiphestTask $task,
    array $map) {

    $table = new ManiphestCustomFieldStorage();
    $conn_w = $table->establishConnection('w');
    $update = array();
    $remove = array();

    foreach ($map as $key => $value) {
      $index = PhabricatorHash::digestForIndex($key);
      if ($value === null) {
        $remove[$index] = true;
      } else {
        $update[$index] = $value;
      }
    }

    if ($remove) {
      queryfx(
        $conn_w,
        'DELETE FROM %T WHERE objectPHID = %s AND fieldIndex IN (%Ls)',
        $table->getTableName(),
        $task->getPHID(),
        array_keys($remove));
    }

    if ($update) {
      $sql = array();
      foreach ($update as $index => $val) {
        $sql[] = qsprintf(
          $conn_w,
          '(%s, %s, %s)',
          $task->getPHID(),
          $index,
          $val);
      }
      queryfx(
        $conn_w,
        'INSERT INTO %T (objectPHID, fieldIndex, fieldValue)
          VALUES %Q ON DUPLICATE KEY
          UPDATE fieldValue = VALUES(fieldValue)',
        $table->getTableName(),
        implode(', ', $sql));
    }

  }

}
