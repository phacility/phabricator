<?php

abstract class PhabricatorFilesManagementWorkflow
  extends PhabricatorManagementWorkflow {

  protected function newIteratorArguments() {
    return array(
      array(
        'name' => 'all',
        'help' => pht('Operate on all files.'),
      ),
      array(
        'name' => 'names',
        'wildcard' => true,
      ),
      array(
        'name' => 'from-engine',
        'param' => 'storage-engine',
        'help' => pht('Operate on files stored in a specified engine.'),
      ),
    );
  }

  protected function buildIterator(PhutilArgumentParser $args) {
    $viewer = $this->getViewer();

    $is_all = $args->getArg('all');

    $names = $args->getArg('names');
    $from_engine = $args->getArg('from-engine');

    $any_constraint = ($from_engine || $names);

    if (!$is_all && !$any_constraint) {
      throw new PhutilArgumentUsageException(
        pht(
          'Specify which files to operate on, or use "--all" to operate on '.
          'all files.'));
    }

    if ($is_all && $any_constraint) {
      throw new PhutilArgumentUsageException(
        pht(
          'You can not operate on all files with "--all" and also operate '.
          'on a subset of files by naming them explicitly or using '.
          'constraint flags like "--from-engine".'));
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
