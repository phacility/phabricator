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
    $this->validateClusterSearchConfig();

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
      echo tsprintf(
        "**<bg:blue> %s </bg>** %s\n",
        pht('NOTE'),
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

    // If we aren't using "--background" or "--force", track how many objects
    // we're skipping so we can print this information for the user and give
    // them a hint that they might want to use "--force".
    $track_skips = (!$is_background && !$is_force);

    $count_updated = 0;
    $count_skipped = 0;

    foreach ($phids as $phid) {
      try {
        if ($track_skips) {
          $old_versions = $this->loadIndexVersions($phid);
        }

        PhabricatorSearchWorker::queueDocumentForIndexing($phid, $parameters);

        if ($track_skips) {
          $new_versions = $this->loadIndexVersions($phid);
          if ($old_versions !== $new_versions) {
            $count_updated++;
          } else {
            $count_skipped++;
          }
        }

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

    if ($track_skips) {
      if ($count_updated) {
        echo tsprintf(
          "**<bg:green> %s </bg>** %s\n",
          pht('DONE'),
          pht(
            'Updated search indexes for %s document(s).',
            new PhutilNumber($count_updated)));
      }

      if ($count_skipped) {
        echo tsprintf(
          "**<bg:yellow> %s </bg>** %s\n",
          pht('SKIP'),
          pht(
            'Skipped %s documents(s) which have not updated since they were '.
            'last indexed.',
            new PhutilNumber($count_skipped)));
        echo tsprintf(
          "**<bg:blue> %s </bg>** %s\n",
          pht('NOTE'),
          pht(
            'Use "--force" to force the index to update these documents.'));
      }
    } else if ($is_background) {
      echo tsprintf(
        "**<bg:green> %s </bg>** %s\n",
        pht('DONE'),
        pht(
          'Queued %s document(s) for background indexing.',
          new PhutilNumber(count($phids))));
    } else {
      echo tsprintf(
        "**<bg:green> %s </bg>** %s\n",
        pht('DONE'),
        pht(
          'Forced search index updates for %s document(s).',
          new PhutilNumber(count($phids))));
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
      ->setAncestorClass('PhabricatorIndexableInterface')
      ->execute();

    $normalized_type = phutil_utf8_strtolower($type);

    $matches = array();
    foreach ($objects as $object) {
      $object_class = get_class($object);
      $normalized_class = phutil_utf8_strtolower($object_class);

      if ($normalized_class === $normalized_type) {
        $matches = array($object_class => $object);
        break;
      }

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

  private function loadIndexVersions($phid) {
    $table = new PhabricatorSearchIndexVersion();
    $conn = $table->establishConnection('r');

    return queryfx_all(
      $conn,
      'SELECT extensionKey, version FROM %T WHERE objectPHID = %s
        ORDER BY extensionKey, version',
      $table->getTableName(),
      $phid);
  }

}
