<?php

abstract class PhabricatorFilesManagementWorkflow
  extends PhabricatorManagementWorkflow {

  protected function buildIterator(PhutilArgumentParser $args) {
    $viewer = $this->getViewer();
    $names = $args->getArg('names');

    $is_all = $args->getArg('all');
    $from_engine = $args->getArg('from-engine');

    $any_constraint = ($from_engine || $names);

    if (!$is_all && !$any_constraint) {
      throw new PhutilArgumentUsageException(
        pht(
          'Use "--all" to migrate all files, or choose files to migrate '.
          'with "--names" or "--from-engine".'));
    }

    if ($is_all && $any_constraint) {
      throw new PhutilArgumentUsageException(
        pht(
          'You can not migrate all files with "--all" and also migrate only '.
          'a subset of files with "--from-engine" or "--names".'));
    }

    // If we're migrating specific named files, convert the names into IDs
    // first.
    $ids = null;
    if ($names) {
      $files = $this->loadFilesWithNames($names);
      $ids = mpull($files, 'getID');
    }

    $query = id(new PhabricatorFileQuery())
      ->setViewer($viewer);

    if ($ids) {
      $query->withIDs($ids);
    }

    if ($from_engine) {
      $query->withStorageEngines(array($from_engine));
    }

    return new PhabricatorQueryIterator($query);
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
            'No file "%s" exists.',
            $name));
      }
    }

    return array_values($files);
  }

}
