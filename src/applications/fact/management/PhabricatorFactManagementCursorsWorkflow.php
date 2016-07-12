<?php

final class PhabricatorFactManagementCursorsWorkflow
  extends PhabricatorFactManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('cursors')
      ->setSynopsis(pht('Show a list of fact iterators and cursors.'))
      ->setExamples(
        "**cursors**\n".
        "**cursors** --reset __cursor__")
      ->setArguments(
        array(
          array(
            'name'    => 'reset',
            'param'   => 'cursor',
            'repeat'  => true,
            'help'    => pht('Reset cursor __cursor__.'),
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $console = PhutilConsole::getConsole();

    $reset = $args->getArg('reset');
    if ($reset) {
      foreach ($reset as $name) {
        $cursor = id(new PhabricatorFactCursor())->loadOneWhere(
          'name = %s',
          $name);
        if ($cursor) {
          $console->writeOut("%s\n", pht('Resetting cursor %s...', $name));
          $cursor->delete();
        } else {
          $console->writeErr(
            "%s\n",
            pht('Cursor %s does not exist or is already reset.', $name));
        }
      }
      return 0;
    }

    $iterator_map = PhabricatorFactDaemon::getAllApplicationIterators();
    if (!$iterator_map) {
      $console->writeErr("%s\n", pht('No cursors.'));
      return 0;
    }

    $cursors = id(new PhabricatorFactCursor())->loadAllWhere(
      'name IN (%Ls)',
      array_keys($iterator_map));
    $cursors = mpull($cursors, 'getPosition', 'getName');

    foreach ($iterator_map as $iterator_name => $iterator) {
      $console->writeOut(
        "%s (%s)\n",
        $iterator_name,
        idx($cursors, $iterator_name, 'start'));
    }

    return 0;
  }

}
