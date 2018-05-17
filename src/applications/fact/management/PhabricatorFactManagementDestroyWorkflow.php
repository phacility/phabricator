<?php

final class PhabricatorFactManagementDestroyWorkflow
  extends PhabricatorFactManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('destroy')
      ->setSynopsis(pht('Destroy all facts.'))
      ->setArguments(array());
  }

  public function execute(PhutilArgumentParser $args) {
    $console = PhutilConsole::getConsole();

    $question = pht(
      'Really destroy all facts? They will need to be rebuilt through '.
      'analysis, which may take some time.');

    $ok = $console->confirm($question, $default = false);
    if (!$ok) {
      return 1;
    }

    $tables = array();
    $tables[] = new PhabricatorFactCursor();

    $tables[] = new PhabricatorFactIntDatapoint();

    $tables[] = new PhabricatorFactObjectDimension();
    $tables[] = new PhabricatorFactKeyDimension();

    foreach ($tables as $table) {
      $conn = $table->establishConnection('w');
      $name = $table->getTableName();

      $console->writeOut("%s\n", pht("Destroying table '%s'...", $name));

      queryfx(
        $conn,
        'TRUNCATE TABLE %T',
        $name);
    }

    $console->writeOut("%s\n", pht('Done.'));
  }

}
