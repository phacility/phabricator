<?php

final class PhabricatorSearchManagementIndexWorkflow
  extends PhabricatorSearchManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('index')
      ->setSynopsis(pht('Build or rebuild search indexes.'))
      ->setExamples(
        implode(
          "\n",
          array(
            '**index** D123',
            '**index** --all',
            '**index** [--type __task__] [--version __version__] ...',
          )))
      ->setArguments(
        array(
          array(
            'name' => 'all',
            'help' => pht('Reindex all documents.'),
          ),
          array(
            'name'  => 'type',
            'param' => 'type',
            'repeat' => true,
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
            'name' => 'version',
            'param' => 'version',
            'repeat' => true,
            'help' => pht(
              'Reindex objects previously indexed with a particular '.
              'version of the indexer.'),
          ),
          array(
            'name' => 'min-index-date',
            'param' => 'date',
            'help' => pht(
              'Reindex objects previously indexed on or after a '.
              'given date.'),
          ),
          array(
            'name' => 'max-index-date',
            'param' => 'date',
            'help' => pht(
              'Reindex objects previously indexed on or before a '.
              'given date.'),
          ),
          array(
            'name'      => 'objects',
            'wildcard'  => true,
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $this->validateClusterSearchConfig();

    $is_all = $args->getArg('all');
    $is_force = $args->getArg('force');

    $object_types = $args->getArg('type');
    $index_versions = $args->getArg('version');

    $min_epoch = $args->getArg('min-index-date');
    if ($min_epoch !== null) {
      $min_epoch = $this->parseTimeArgument($min_epoch);
    }

    $max_epoch = $args->getArg('max-index-date');
    if ($max_epoch !== null) {
      $max_epoch = $this->parseTimeArgument($max_epoch);
    }

    $object_names = $args->getArg('objects');

    $any_constraints =
      ($object_names) ||
      ($object_types) ||
      ($index_versions) ||
      ($min_epoch) ||
      ($max_epoch);

    if ($is_all && $any_constraints) {
      throw new PhutilArgumentUsageException(
        pht(
          'You can not use query constraint flags (like "--version", '.
          '"--type", or a list of specific objects) with "--all".'));
    }

    if (!$is_all && !$any_constraints) {
      throw new PhutilArgumentUsageException(
        pht(
          'Provide a list of objects to index (like "D123"), or a set of '.
          'query constraint flags (like "--type"), or "--all" to index '.
          'all objects.'));
    }


    if ($args->getArg('background')) {
      $is_background = true;
    } else {
      PhabricatorWorker::setRunAllTasksInProcess(true);
      $is_background = false;
    }

    if (!$is_background) {
      $this->logInfo(
        pht('NOTE'),
        pht(
          'Run this workflow with "--background" to queue tasks for the '.
          'daemon workers.'));
    }

    $this->logInfo(
      pht('SELECT'),
      pht('Selecting objects to index...'));

    $object_phids = null;
    if ($object_names) {
      $object_phids = $this->loadPHIDsByNames($object_names);
      $object_phids = array_fuse($object_phids);
    }

    $type_phids = null;
    if ($is_all || $object_types) {
      $object_map = $this->getIndexableObjectsByTypes($object_types);
      $type_phids = array();
      foreach ($object_map as $object) {
        $iterator = new LiskMigrationIterator($object);
        foreach ($iterator as $o) {
          $type_phids[] = $o->getPHID();
        }
      }
      $type_phids = array_fuse($type_phids);
    }

    $index_phids = null;
    if ($index_versions || $min_epoch || $max_epoch) {
      $index_phids = $this->loadPHIDsByIndexConstraints(
        $index_versions,
        $min_epoch,
        $max_epoch);
      $index_phids = array_fuse($index_phids);
    }

    $working_set = null;
    $filter_sets = array(
      $object_phids,
      $type_phids,
      $index_phids,
    );

    foreach ($filter_sets as $filter_set) {
      if ($filter_set === null) {
        continue;
      }

      if ($working_set === null) {
        $working_set = $filter_set;
        continue;
      }

      $working_set = array_intersect_key($working_set, $filter_set);
    }

    $phids = array_keys($working_set);

    if (!$phids) {
      $this->logWarn(
        pht('NO OBJECTS'),
        pht('No objects selected to index.'));
      return 0;
    }

    $this->logInfo(
      pht('INDEXING'),
      pht(
        'Indexing %s object(s).',
        phutil_count($phids)));

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

    // Activate "strict" error reporting if we're running in the foreground
    // so we'll report a wider range of conditions as errors.
    $is_strict = !$is_background;

    $count_updated = 0;
    $count_skipped = 0;

    foreach ($phids as $phid) {
      try {
        if ($track_skips) {
          $old_versions = $this->loadIndexVersions($phid);
        }

        PhabricatorSearchWorker::queueDocumentForIndexing(
          $phid,
          $parameters,
          $is_strict);

        if ($track_skips) {
          $new_versions = $this->loadIndexVersions($phid);

          if (!$old_versions && !$new_versions) {
            // If the document doesn't use an index version, both the lists
            // of versions will be empty. We still rebuild the index in this
            // case.
            $count_updated++;
          } else if ($old_versions !== $new_versions) {
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
        $this->logOkay(
          pht('DONE'),
          pht(
            'Updated search indexes for %s document(s).',
            new PhutilNumber($count_updated)));
      }

      if ($count_skipped) {
        $this->logWarn(
          pht('SKIP'),
          pht(
            'Skipped %s documents(s) which have not updated since they were '.
            'last indexed.',
            new PhutilNumber($count_skipped)));
        $this->logInfo(
          pht('NOTE'),
          pht(
            'Use "--force" to force the index to update these documents.'));
      }
    } else if ($is_background) {
      $this->logOkay(
        pht('DONE'),
        pht(
          'Queued %s document(s) for background indexing.',
          new PhutilNumber(count($phids))));
    } else {
      $this->logOkay(
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

  private function getIndexableObjectsByTypes(array $types) {
    $objects = id(new PhutilClassMapQuery())
      ->setAncestorClass('PhabricatorIndexableInterface')
      ->execute();

    $type_map = array();
    $normal_map = array();
    foreach ($types as $type) {
      $normalized_type = phutil_utf8_strtolower($type);
      $type_map[$type] = $normalized_type;

      if (isset($normal_map[$normalized_type])) {
        $old_type = $normal_map[$normalized_type];
        throw new PhutilArgumentUsageException(
          pht(
            'Type specification "%s" duplicates type specification "%s". '.
            'Specify each type only once.',
            $type,
            $old_type));
      }

      $normal_map[$normalized_type] = $type;
    }

    $object_matches = array();

    $matches_map = array();
    $exact_map = array();
    foreach ($objects as $object) {
      $object_class = get_class($object);

      if (!$types) {
        $object_matches[$object_class] = $object;
        continue;
      }

      $normalized_class = phutil_utf8_strtolower($object_class);

      // If a specified type is exactly the name of this class, match it.
      if (isset($normal_map[$normalized_class])) {
        $object_matches[$object_class] = $object;
        $matching_type = $normal_map[$normalized_class];
        $matches_map[$matching_type] = array($object_class);
        $exact_map[$matching_type] = true;
        continue;
      }

      foreach ($type_map as $type => $normalized_type) {
        // If we already have an exact match for this type, don't match it
        // as a substring. An indexable "MothObject" should be selectable
        // exactly without also selecting "MammothObject".
        if (isset($exact_map[$type])) {
          continue;
        }

        // If the selector isn't a substring of the class name, continue.
        if (strpos($normalized_class, $normalized_type) === false) {
          continue;
        }

        $matches_map[$type][] = $object_class;
        $object_matches[$object_class] = $object;
      }
    }

    $all_types = array();
    foreach ($objects as $object) {
      $all_types[] = get_class($object);
    }
    sort($all_types);
    $type_list = implode(', ', $all_types);

    foreach ($type_map as $type => $normalized_type) {
      $matches = idx($matches_map, $type);
      if (!$matches) {
        throw new PhutilArgumentUsageException(
          pht(
            'Type "%s" matches no indexable objects. '.
            'Supported types are: %s.',
            $type,
            $type_list));
      }

      if (count($matches) > 1) {
        throw new PhutilArgumentUsageException(
          pht(
            'Type "%s" matches multiple indexable objects. Use a more '.
            'specific string. Matching objects are: %s.',
            $type,
            implode(', ', $matches)));
      }
    }

    return $object_matches;
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

  private function loadPHIDsByIndexConstraints(
    array $index_versions,
    $min_date,
    $max_date) {

    $table = new PhabricatorSearchIndexVersion();
    $conn = $table->establishConnection('r');

    $where = array();
    if ($index_versions) {
      $where[] = qsprintf(
        $conn,
        'indexVersion IN (%Ls)',
        $index_versions);
    }

    if ($min_date !== null) {
      $where[] = qsprintf(
        $conn,
        'indexEpoch >= %d',
        $min_date);
    }

    if ($max_date !== null) {
      $where[] = qsprintf(
        $conn,
        'indexEpoch <= %d',
        $max_date);
    }

    $rows = queryfx_all(
      $conn,
      'SELECT DISTINCT objectPHID FROM %R WHERE %LA',
      $table,
      $where);

    return ipull($rows, 'objectPHID');
  }

}
