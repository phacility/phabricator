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

    echo $file->loadFileData();

    return 0;
  }

}
