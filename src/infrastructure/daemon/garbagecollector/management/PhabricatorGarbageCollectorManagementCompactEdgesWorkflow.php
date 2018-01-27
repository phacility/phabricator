<?php

final class PhabricatorGarbageCollectorManagementCompactEdgesWorkflow
  extends PhabricatorGarbageCollectorManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('compact-edges')
      ->setExamples('**compact-edges**')
      ->setSynopsis(
        pht(
          'Rebuild old edge transactions storage to use a more compact '.
          'format.'))
      ->setArguments(array());
  }

  public function execute(PhutilArgumentParser $args) {
    $tables = id(new PhutilClassMapQuery())
      ->setAncestorClass('PhabricatorApplicationTransaction')
      ->execute();

    foreach ($tables as $table) {
      $this->compactEdges($table);
    }

    return 0;
  }

  private function compactEdges(PhabricatorApplicationTransaction $table) {
    $conn = $table->establishConnection('w');
    $class = get_class($table);

    echo tsprintf(
      "%s\n",
      pht(
        'Rebuilding transactions for "%s"...',
        $class));

    $cursor = 0;
    $updated = 0;
    while (true) {
      $rows = $table->loadAllWhere(
        'transactionType = %s
          AND id > %d
          AND (oldValue LIKE %> OR newValue LIKE %>)
          ORDER BY id ASC LIMIT 100',
        PhabricatorTransactions::TYPE_EDGE,
        $cursor,
        // We're looking for transactions with JSON objects in their value
        // fields: the new style transactions have JSON lists instead and
        // start with "[" rather than "{".
        '{',
        '{');

      if (!$rows) {
        break;
      }

      foreach ($rows as $row) {
        $id = $row->getID();

        $old = $row->getOldValue();
        $new = $row->getNewValue();

        if (!is_array($old) || !is_array($new)) {
          echo tsprintf(
            "%s\n",
            pht(
              'Transaction %s (of type %s) has unexpected data, skipping.',
              $id,
              $class));
        }

        $record = PhabricatorEdgeChangeRecord::newFromTransaction($row);

        $old_data = $record->getModernOldEdgeTransactionData();
        $old_json = phutil_json_encode($old_data);

        $new_data = $record->getModernNewEdgeTransactionData();
        $new_json = phutil_json_encode($new_data);

        queryfx(
          $conn,
          'UPDATE %T SET oldValue = %s, newValue = %s WHERE id = %d',
          $table->getTableName(),
          $old_json,
          $new_json,
          $id);

        $updated++;

        $cursor = $row->getID();
      }
    }

    echo tsprintf(
      "%s\n",
      pht(
        'Done, compacted %s edge transactions.',
        new PhutilNumber($updated)));
  }

}
