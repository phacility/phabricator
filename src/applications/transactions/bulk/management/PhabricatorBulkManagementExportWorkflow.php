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
              'Export the data selected by this query.'),
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

    $class = $args->getArg('class');

    if (!strlen($class)) {
      throw new PhutilArgumentUsageException(
        pht(
          'Specify a search engine class to export data from with '.
          '"--class".'));
    }

    if (!is_subclass_of($class, 'PhabricatorApplicationSearchEngine')) {
      throw new PhutilArgumentUsageException(
        pht(
          'SearchEngine class ("%s") is unknown.',
          $class));
    }

    $engine = newv($class, array())
      ->setViewer($viewer);

    if (!$engine->canExport()) {
      throw new PhutilArgumentUsageException(
        pht(
          'SearchEngine class ("%s") does not support data export.',
          $class));
    }

    $query_key = $args->getArg('query');
    if (!strlen($query_key)) {
      throw new PhutilArgumentUsageException(
        pht(
          'Specify a query to export with "--query".'));
    }

    if ($engine->isBuiltinQuery($query_key)) {
      $saved_query = $engine->buildSavedQueryFromBuiltin($query_key);
    } else if ($query_key) {
      $saved_query = id(new PhabricatorSavedQueryQuery())
        ->setViewer($viewer)
        ->withQueryKeys(array($query_key))
        ->executeOne();
    } else {
      $saved_query = null;
    }

    if (!$saved_query) {
      throw new PhutilArgumentUsageException(
        pht(
          'Failed to load saved query ("%s").',
          $query_key));
    }

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

    if (!strlen($output_path) && $is_overwrite) {
      throw new PhutilArgumentUsageException(
        pht(
          'Flag "--overwrite" has no effect without "--output".'));
    }

    if (!$is_overwrite) {
      if (Filesystem::pathExists($output_path)) {
        throw new PhutilArgumentUsageException(
          pht(
            'Output path already exists. Use "--overwrite" to overwrite '.
            'it.'));
      }
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

    if (strlen($output_path)) {
      foreach ($iterator as $chunk) {
        Filesystem::appendFile($output_path, $chunk);
      }
    } else {
      foreach ($iterator as $chunk) {
        echo $chunk;
      }
    }

    return 0;
  }

}
