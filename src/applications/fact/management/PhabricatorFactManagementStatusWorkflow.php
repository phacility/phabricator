<?php

final class PhabricatorFactManagementStatusWorkflow
  extends PhabricatorFactManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('status')
      ->setSynopsis(pht('Show status of fact data.'))
      ->setArguments(array());
  }

  public function execute(PhutilArgumentParser $args) {
    $console = PhutilConsole::getConsole();

    $map = array(
      'raw' => new PhabricatorFactRaw(),
      'agg' => new PhabricatorFactAggregate(),
    );

    foreach ($map as $type => $table) {
      $conn = $table->establishConnection('r');
      $name = $table->getTableName();

      $row = queryfx_one(
        $conn,
        'SELECT COUNT(*) N FROM %T',
        $name);

      $n = $row['N'];

      switch ($type) {
        case 'raw':
          $desc = pht('There are %d raw fact(s) in storage.', $n);
          break;
        case 'agg':
          $desc = pht('There are %d aggregate fact(s) in storage.', $n);
          break;
      }

      $console->writeOut("%s\n", $desc);
    }

    return 0;
  }

}
