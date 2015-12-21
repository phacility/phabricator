<?php

final class PhabricatorSearchManagementIndexWorkflow
  extends PhabricatorSearchManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('index')
      ->setSynopsis(pht('Build or rebuild search indexes.'))
      ->setExamples(
        "**index** D123\n".
        "**index** --type task\n".
        "**index** --all")
      ->setArguments(
        array(
          array(
            'name' => 'all',
            'help' => pht('Reindex all documents.'),
          ),
          array(
            'name'  => 'type',
            'param' => 'type',
            'help'  => pht(
              'Object types to reindex, like "task", "commit" or "revision".'),
          ),
          array(
            'name' => 'background',
            'help' => pht(
              'Instead of indexing in this process, queue tasks for '.
              'the daemons. This can improve performance, but makes '.
              'it more difficult to debug search indexing.'),
          ),
          array(
            'name' => 'force',
            'short' => 'f',
            'help' => pht(
              'Force a complete rebuild of the entire index instead of an '.
              'incremental update.'),
          ),
          array(
            'name'      => 'objects',
            'wildcard'  => true,
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $console = PhutilConsole::getConsole();

    $is_all = $args->getArg('all');
    $is_type = $args->getArg('type');
    $is_force = $args->getArg('force');

    $obj_names = $args->getArg('objects');

    if ($obj_names && ($is_all || $is_type)) {
      throw new PhutilArgumentUsageException(
        pht(
          "You can not name objects to index alongside the '%s' or '%s' flags.",
          '--all',
          '--type'));
    } else if (!$obj_names && !($is_all || $is_type)) {
      throw new PhutilArgumentUsageException(
        pht(
          "Provide one of '%s', '%s' or a list of object names.",
          '--all',
          '--type'));
    }

    if ($obj_names) {
      $phids = $this->loadPHIDsByNames($obj_names);
    } else {
      $phids = $this->loadPHIDsByTypes($is_type);
    }

    if (!$phids) {
      throw new PhutilArgumentUsageException(pht('Nothing to index!'));
    }

    if ($args->getArg('background')) {
      $is_background = true;
    } else {
      PhabricatorWorker::setRunAllTasksInProcess(true);
      $is_background = false;
    }

    if (!$is_background) {
      $console->writeOut(
        "%s\n",
        pht(
          'Run this workflow with "%s" to queue tasks for the daemon workers.',
          '--background'));
    }

    $groups = phid_group_by_type($phids);
    foreach ($groups as $group_type => $group) {
      $console->writeOut(
        "%s\n",
        pht('Indexing %d object(s) of type %s.', count($group), $group_type));
    }

    $bar = id(new PhutilConsoleProgressBar())
      ->setTotal(count($phids));

    $parameters = array(
      'force' => $is_force,
    );

    $any_success = false;
    foreach ($phids as $phid) {
      try {
        PhabricatorSearchWorker::queueDocumentForIndexing($phid, $parameters);
        $any_success = true;
      } catch (Exception $ex) {
        phlog($ex);
      }

      $bar->update(1);
    }

    $bar->done();

    if (!$any_success) {
      throw new Exception(
        pht('Failed to rebuild search index for any documents.'));
    }

  }

  private function loadPHIDsByNames(array $names) {
    $query = id(new PhabricatorObjectQuery())
      ->setViewer($this->getViewer())
      ->withNames($names);
    $query->execute();
    $objects = $query->getNamedResults();

    foreach ($names as $name) {
      if (empty($objects[$name])) {
        throw new PhutilArgumentUsageException(
          pht(
            "'%s' is not the name of a known object.",
            $name));
      }
    }

    return mpull($objects, 'getPHID');
  }

  private function loadPHIDsByTypes($type) {
    $objects = id(new PhutilClassMapQuery())
      ->setAncestorClass('PhabricatorFulltextInterface')
      ->execute();

    $normalized_type = phutil_utf8_strtolower($type);

    $matches = array();
    foreach ($objects as $object) {
      $object_class = get_class($object);
      $normalized_class = phutil_utf8_strtolower($object_class);

      if (!strlen($type) ||
          strpos($normalized_class, $normalized_type) !== false) {
        $matches[$object_class] = $object;
      }
    }

    if (!$matches) {
      $all_types = array();
      foreach ($objects as $object) {
        $all_types[] = get_class($object);
      }
      sort($all_types);

      throw new PhutilArgumentUsageException(
        pht(
          'Type "%s" matches no indexable objects. Supported types are: %s.',
          $type,
          implode(', ', $all_types)));
    }

    if ((count($matches) > 1) && strlen($type)) {
      throw new PhutilArgumentUsageException(
        pht(
          'Type "%s" matches multiple indexable objects. Use a more '.
          'specific string. Matching object types are: %s.',
          $type,
          implode(', ', array_keys($matches))));
    }

    $phids = array();
    foreach ($matches as $match) {
      $iterator = new LiskMigrationIterator($match);
      foreach ($iterator as $object) {
        $phids[] = $object->getPHID();
      }
    }

    return $phids;
  }


}
