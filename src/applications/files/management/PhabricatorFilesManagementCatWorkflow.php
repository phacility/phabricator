<?php

final class PhabricatorFilesManagementCatWorkflow
  extends PhabricatorFilesManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('cat')
      ->setSynopsis(
        pht('Print the contents of a file.'))
      ->setArguments(
        array(
          array(
            'name' => 'begin',
            'param' => 'bytes',
            'help' => pht('Begin printing at a specific offset.'),
          ),
          array(
            'name' => 'end',
            'param' => 'bytes',
            'help' => pht('End printing at a specific offset.'),
          ),
          array(
            'name'      => 'names',
            'wildcard'  => true,
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $console = PhutilConsole::getConsole();

    $names = $args->getArg('names');
    if (count($names) > 1) {
      throw new PhutilArgumentUsageException(
        pht('Specify exactly one file to print, like "F123".'));
    } else if (!$names) {
      throw new PhutilArgumentUsageException(
        pht('Specify a file to print, like "F123".'));
    }

    $file = head($this->loadFilesWithNames($names));

    $begin = $args->getArg('begin');
    $end = $args->getArg('end');

    $iterator = $file->getFileDataIterator($begin, $end);
    foreach ($iterator as $data) {
      echo $data;
    }

    return 0;
  }

}
