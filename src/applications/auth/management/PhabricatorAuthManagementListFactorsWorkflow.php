<?php

final class PhabricatorAuthManagementListFactorsWorkflow
  extends PhabricatorAuthManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('list-factors')
      ->setExamples('**list-factors**')
      ->setSynopsis(pht('List available multi-factor authentication factors.'))
      ->setArguments(array());
  }

  public function execute(PhutilArgumentParser $args) {
    $factors = PhabricatorAuthFactor::getAllFactors();

    $console = PhutilConsole::getConsole();
    foreach ($factors as $factor) {
      $console->writeOut(
        "%s\t%s\n",
        $factor->getFactorKey(),
        $factor->getFactorName());
    }

    return 0;
  }

}
