<?php

final class PhabricatorExportEngineBulkJobType
   extends PhabricatorWorkerSingleBulkJobType {

  public function getBulkJobTypeKey() {
    return 'export';
  }

  public function getJobName(PhabricatorWorkerBulkJob $job) {
    return pht('Data Export');
  }

  public function getCurtainActions(
    PhabricatorUser $viewer,
    PhabricatorWorkerBulkJob $job) {
    $actions = array();

    $file_phid = $job->getParameter('filePHID');
    if (!$file_phid) {
      $actions[] = id(new PhabricatorActionView())
        ->setHref('#')
        ->setIcon('fa-download')
        ->setDisabled(true)
        ->setName(pht('Exporting Data...'));
    } else {
      $file = id(new PhabricatorFileQuery())
        ->setViewer($viewer)
        ->withPHIDs(array($file_phid))
        ->executeOne();
      if (!$file) {
        $actions[] = id(new PhabricatorActionView())
          ->setHref('#')
          ->setIcon('fa-download')
          ->setDisabled(true)
          ->setName(pht('Temporary File Expired'));
      } else {
        $actions[] = id(new PhabricatorActionView())
          ->setHref($file->getDownloadURI())
          ->setIcon('fa-download')
          ->setName(pht('Download Data Export'));
      }
    }

    return $actions;
  }


  public function runTask(
    PhabricatorUser $actor,
    PhabricatorWorkerBulkJob $job,
    PhabricatorWorkerBulkTask $task) {

    $engine_class = $job->getParameter('engineClass');
    if (!is_subclass_of($engine_class, 'PhabricatorApplicationSearchEngine')) {
      throw new Exception(
        pht(
          'Unknown search engine class "%s".',
          $engine_class));
    }

    $engine = newv($engine_class, array())
      ->setViewer($actor);

    $query_key = $job->getParameter('queryKey');
    if ($engine->isBuiltinQuery($query_key)) {
      $saved_query = $engine->buildSavedQueryFromBuiltin($query_key);
    } else if ($query_key) {
      $saved_query = id(new PhabricatorSavedQueryQuery())
        ->setViewer($actor)
        ->withQueryKeys(array($query_key))
        ->executeOne();
    } else {
      $saved_query = null;
    }

    if (!$saved_query) {
      throw new Exception(
        pht(
          'Failed to load saved query ("%s").',
          $query_key));
    }

    $format_key = $job->getParameter('formatKey');

    $all_formats = PhabricatorExportFormat::getAllExportFormats();
    $format = idx($all_formats, $format_key);
    if (!$format) {
      throw new Exception(
        pht(
          'Unknown export format ("%s").',
          $format_key));
    }

    if (!$format->isExportFormatEnabled()) {
      throw new Exception(
        pht(
          'Export format ("%s") is not enabled.',
          $format_key));
    }

    $export_engine = id(new PhabricatorExportEngine())
      ->setViewer($actor)
      ->setTitle($job->getParameter('title'))
      ->setFilename($job->getParameter('filename'))
      ->setSearchEngine($engine)
      ->setSavedQuery($saved_query)
      ->setExportFormat($format);

    $file = $export_engine->exportFile();

    $job
      ->setParameter('filePHID', $file->getPHID())
      ->save();
  }

}
