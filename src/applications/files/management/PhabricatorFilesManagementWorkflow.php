<?php

abstract class PhabricatorFilesManagementWorkflow
  extends PhabricatorManagementWorkflow {

  protected function buildIterator(PhutilArgumentParser $args) {
    $names = $args->getArg('names');

    if ($args->getArg('all')) {
      if ($names) {
        throw new PhutilArgumentUsageException(
          pht(
            'Specify either a list of files or `%s`, but not both.',
            '--all'));
      }
      return new LiskMigrationIterator(new PhabricatorFile());
    }

    if ($names) {
      return $this->loadFilesWithNames($names);
    }

    return null;
  }

  protected function loadFilesWithNames(array $names) {
    $query = id(new PhabricatorObjectQuery())
      ->setViewer($this->getViewer())
      ->withNames($names)
      ->withTypes(array(PhabricatorFileFilePHIDType::TYPECONST));

    $query->execute();
    $files = $query->getNamedResults();

    foreach ($names as $name) {
      if (empty($files[$name])) {
        throw new PhutilArgumentUsageException(
          pht(
            "No file '%s' exists!",
            $name));
      }
    }

    return array_values($files);
  }

}
