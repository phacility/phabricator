<?php

final class PhabricatorSystemRemoveLogWorkflow
  extends PhabricatorSystemRemoveWorkflow {

  protected function didConstruct() {
    $this
      ->setName('log')
      ->setSynopsis(pht('Show a log of permanently destroyed objects.'))
      ->setExamples('**log**')
      ->setArguments(array());
  }

  public function execute(PhutilArgumentParser $args) {
    $console = PhutilConsole::getConsole();

    $table = new PhabricatorSystemDestructionLog();
    foreach (new LiskMigrationIterator($table) as $row) {
      $console->writeOut(
        "[%s]\t%s %s\t%s\t%s\n",
        phabricator_datetime($row->getEpoch(), $this->getViewer()),
        ($row->getRootLogID() ? ' ' : '*'),
        $row->getObjectClass(),
        $row->getObjectPHID(),
        $row->getObjectMonogram());
    }

    return 0;
  }

}
