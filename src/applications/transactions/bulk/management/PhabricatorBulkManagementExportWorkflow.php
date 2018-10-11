<?php

final class PhabricatorBulkManagementExportWorkflow
  extends PhabricatorBulkManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('export')
      ->setExamples('**export** [options]')
      ->setSynopsis(
        pht('Export data to a flat file (JSON, CSV, Excel, etc).'))
      ->setArguments(
        array(
          array(
            'name' => 'class',
            'param' => 'class',
            'help' => pht(
              'SearchEngine class to export data from.'),
          ),
          array(
            'name' => 'format',
            'param' => 'format',
            'help' => pht('Export format.'),
          ),
          array(
            'name' => 'query',
            'param' => 'key',
            'help' => pht(
              'Export the data selected by one or more queries.'),
            'repeat' => true,
          ),
          array(
            'name' => 'output',
            'param' => 'path',
            'help' => pht(
              'Write output to a file. If omitted, output will be sent to '.
              'stdout.'),
          ),
          array(
            'name' => 'overwrite',
            'help' => pht(
              'If the output file already exists, overwrite it instead of '.
              'raising an error.'),
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $viewer = $this->getViewer();

    list($engine, $queries) = $this->newQueries($args);

    $format_key = $args->getArg('format');
    if (!strlen($format_key)) {
      throw new PhutilArgumentUsageException(
        pht(
          'Specify an export format with "--format".'));
    }

    $all_formats = PhabricatorExportFormat::getAllExportFormats();
    $format = idx($all_formats, $format_key);
    if (!$format) {
      throw new PhutilArgumentUsageException(
        pht(
          'Unknown export format ("%s"). Known formats are: %s.',
          $format_key,
          implode(', ', array_keys($all_formats))));
    }

    if (!$format->isExportFormatEnabled()) {
      throw new PhutilArgumentUsageException(
        pht(
          'Export format ("%s") is not enabled.',
          $format_key));
    }

    $is_overwrite = $args->getArg('overwrite');
    $output_path = $args->getArg('output');

    if (!strlen($output_path)) {
      throw new PhutilArgumentUsageException(
        pht(
          'Use "--output <path>" to specify an output file, or "--output -" '.
          'to print to stdout.'));
    }

    if ($output_path === '-') {
      $is_stdout = true;
    } else {
      $is_stdout = false;
    }

    if ($is_stdout && $is_overwrite) {
      throw new PhutilArgumentUsageException(
        pht(
          'Flag "--overwrite" has no effect when outputting to stdout.'));
    }

    if (!$is_overwrite) {
      if (!$is_stdout && Filesystem::pathExists($output_path)) {
        throw new PhutilArgumentUsageException(
          pht(
            'Output path already exists. Use "--overwrite" to overwrite '.
            'it.'));
      }
    }

    // If we have more than one query, execute the queries to figure out which
    // results they hit, then build a synthetic query for all those results
    // using the IDs.
    if (count($queries) > 1) {
      $saved_query = $this->newUnionQuery($engine, $queries);
    } else {
      $saved_query = head($queries);
    }

    $export_engine = id(new PhabricatorExportEngine())
      ->setViewer($viewer)
      ->setTitle(pht('Export'))
      ->setFilename(pht('export'))
      ->setSearchEngine($engine)
      ->setSavedQuery($saved_query)
      ->setExportFormat($format);

    $file = $export_engine->exportFile();

    $iterator = $file->getFileDataIterator();

    if (!$is_stdout) {
      // Empty the file before we start writing to it. Otherwise, "--overwrite"
      // will really mean "--append".
      Filesystem::writeFile($output_path, '');

      foreach ($iterator as $chunk) {
        Filesystem::appendFile($output_path, $chunk);
      }

      echo tsprintf(
        "%s\n",
        pht(
          'Exported data to "%s".',
          Filesystem::readablePath($output_path)));
    } else {
      foreach ($iterator as $chunk) {
        echo $chunk;
      }
    }

    return 0;
  }

  private function newQueries(PhutilArgumentParser $args) {
    $viewer = $this->getViewer();

    $query_keys = $args->getArg('query');
    if (!$query_keys) {
      throw new PhutilArgumentUsageException(
        pht(
          'Specify one or more queries to export with "--query".'));
    }

    $engine_classes = id(new PhutilClassMapQuery())
      ->setAncestorClass('PhabricatorApplicationSearchEngine')
      ->execute();

    $class = $args->getArg('class');
    if (strlen($class)) {

      $class_list = array();
      foreach ($engine_classes as $class_name => $engine_object) {
        $can_export = id(clone $engine_object)
          ->setViewer($viewer)
          ->canExport();
        if ($can_export) {
          $class_list[] = $class_name;
        }
      }

      sort($class_list);
      $class_list = implode(', ', $class_list);

      $matches = array();
      foreach ($engine_classes as $class_name => $engine_object) {
        if (stripos($class_name, $class) !== false) {
          if (strtolower($class_name) == strtolower($class)) {
            $matches = array($class_name);
            break;
          } else {
            $matches[] = $class_name;
          }
        }
      }

      if (!$matches) {
        throw new PhutilArgumentUsageException(
          pht(
            'No search engines match "%s". Available engines which support '.
            'data export are: %s.',
            $class,
            $class_list));
      } else if (count($matches) > 1) {
        throw new PhutilArgumentUsageException(
          pht(
            'Multiple search engines match "%s": %s.',
            $class,
            implode(', ', $matches)));
      } else {
        $class = head($matches);
      }

      $engine = newv($class, array())
        ->setViewer($viewer);
    } else {
      $engine = null;
    }

    $queries = array();
    foreach ($query_keys as $query_key) {
      if ($engine) {
        if ($engine->isBuiltinQuery($query_key)) {
          $queries[$query_key] = $engine->buildSavedQueryFromBuiltin(
            $query_key);
          continue;
        }
      }

      $saved_query = id(new PhabricatorSavedQueryQuery())
        ->setViewer($viewer)
        ->withQueryKeys(array($query_key))
        ->executeOne();
      if (!$saved_query) {
        if (!$engine) {
          throw new PhutilArgumentUsageException(
            pht(
              'Query "%s" is unknown. To run a builtin query like "all" or '.
              '"active", also specify the search engine with "--class".',
              $query_key));
        } else {
          throw new PhutilArgumentUsageException(
            pht(
              'Query "%s" is not a recognized query for class "%s".',
              $query_key,
              get_class($engine)));
        }
      }

      $queries[$query_key] = $saved_query;
    }

    // If we don't have an engine from "--class", fill it in by looking at the
    // class of the first query.
    if (!$engine) {
      foreach ($queries as $query) {
        $engine = newv($query->getEngineClassName(), array())
          ->setViewer($viewer);
        break;
      }
    }

    $engine_class = get_class($engine);

    foreach ($queries as $query) {
      $query_class = $query->getEngineClassName();
      if ($query_class !== $engine_class) {
        throw new PhutilArgumentUsageException(
          pht(
            'Specified queries use different engines: query "%s" uses '.
            'engine "%s", not "%s". All queries must run on the same '.
            'engine.',
            $query->getQueryKey(),
            $query_class,
            $engine_class));
      }
    }

    if (!$engine->canExport()) {
      throw new PhutilArgumentUsageException(
        pht(
          'SearchEngine class ("%s") does not support data export.',
          $engine_class));
    }

    return array($engine, $queries);
  }

  private function newUnionQuery(
    PhabricatorApplicationSearchEngine $engine,
    array $queries) {

    assert_instances_of($queries, 'PhabricatorSavedQuery');

    $engine = clone $engine;

    $ids = array();
    foreach ($queries as $saved_query) {
      $page_size = 1000;
      $page_cursor = null;
      do {
        $query = $engine->buildQueryFromSavedQuery($saved_query);
        $pager = $engine->newPagerForSavedQuery($saved_query);
        $pager->setPageSize($page_size);

        if ($page_cursor !== null) {
          $pager->setAfterID($page_cursor);
        }

        $objects = $engine->executeQuery($query, $pager);
        $page_cursor = $pager->getNextPageID();

        foreach ($objects as $object) {
          $ids[] = $object->getID();
        }
      } while ($pager->getHasMoreResults());
    }

    // When we're merging multiple different queries, override any query order
    // and just put the combined result list in ID order. At time of writing,
    // we can't merge the result sets together while retaining the overall sort
    // order even if they all used the same order, and it's meaningless to try
    // to retain orders if the queries had different orders in the first place.
    rsort($ids);

    return id($engine->newSavedQuery())
      ->setParameter('ids', $ids);
  }

}
