<?php

final class PhabricatorExportEngine
  extends Phobject {

  private $viewer;
  private $searchEngine;
  private $savedQuery;
  private $exportFormat;
  private $filename;
  private $title;

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public function getViewer() {
    return $this->viewer;
  }

  public function setSearchEngine(
    PhabricatorApplicationSearchEngine $search_engine) {
    $this->searchEngine = $search_engine;
    return $this;
  }

  public function getSearchEngine() {
    return $this->searchEngine;
  }

  public function setSavedQuery(PhabricatorSavedQuery $saved_query) {
    $this->savedQuery = $saved_query;
    return $this;
  }

  public function getSavedQuery() {
    return $this->savedQuery;
  }

  public function setExportFormat(
    PhabricatorExportFormat $export_format) {
    $this->exportFormat = $export_format;
    return $this;
  }

  public function getExportFormat() {
    return $this->exportFormat;
  }

  public function setFilename($filename) {
    $this->filename = $filename;
    return $this;
  }

  public function getFilename() {
    return $this->filename;
  }

  public function setTitle($title) {
    $this->title = $title;
    return $this;
  }

  public function getTitle() {
    return $this->title;
  }

  public function newBulkJob(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $engine = $this->getSearchEngine();
    $saved_query = $this->getSavedQuery();
    $format = $this->getExportFormat();

    $params = array(
      'engineClass' => get_class($engine),
      'queryKey' => $saved_query->getQueryKey(),
      'formatKey' => $format->getExportFormatKey(),
      'title' => $this->getTitle(),
      'filename' => $this->getFilename(),
    );

    $job = PhabricatorWorkerBulkJob::initializeNewJob(
      $viewer,
      new PhabricatorExportEngineBulkJobType(),
      $params);

    // We queue these jobs directly into STATUS_WAITING without requiring
    // a confirmation from the user.

    $xactions = array();

    $xactions[] = id(new PhabricatorWorkerBulkJobTransaction())
      ->setTransactionType(PhabricatorWorkerBulkJobTransaction::TYPE_STATUS)
      ->setNewValue(PhabricatorWorkerBulkJob::STATUS_WAITING);

    $editor = id(new PhabricatorWorkerBulkJobEditor())
      ->setActor($viewer)
      ->setContentSourceFromRequest($request)
      ->setContinueOnMissingFields(true)
      ->applyTransactions($job, $xactions);

    return $job;
  }

  public function exportFile() {
    $viewer = $this->getViewer();
    $engine = $this->getSearchEngine();
    $saved_query = $this->getSavedQuery();
    $format = $this->getExportFormat();
    $title = $this->getTitle();
    $filename = $this->getFilename();

    $query = $engine->buildQueryFromSavedQuery($saved_query);

    $extension = $format->getFileExtension();
    $mime_type = $format->getMIMEContentType();
    $filename = $filename.'.'.$extension;

    $format = id(clone $format)
      ->setViewer($viewer)
      ->setTitle($title);

    $field_list = $engine->newExportFieldList();
    $field_list = mpull($field_list, null, 'getKey');
    $format->addHeaders($field_list);

    // Iterate over the query results in large pages so we don't have to hold
    // too much stuff in memory.
    $page_size = 1000;
    $page_cursor = null;
    do {
      $pager = $engine->newPagerForSavedQuery($saved_query);
      $pager->setPageSize($page_size);

      if ($page_cursor !== null) {
        $pager->setAfterID($page_cursor);
      }

      $objects = $engine->executeQuery($query, $pager);
      $objects = array_values($objects);
      $page_cursor = $pager->getNextPageID();

      $export_data = $engine->newExport($objects);
      for ($ii = 0; $ii < count($objects); $ii++) {
        $format->addObject($objects[$ii], $field_list, $export_data[$ii]);
      }
    } while ($pager->getHasMoreResults());

    $export_result = $format->newFileData();

    // We have all the data in one big string and aren't actually
    // streaming it, but pretending that we are allows us to actviate
    // the chunk engine and store large files.
    $iterator = new ArrayIterator(array($export_result));

    $source = id(new PhabricatorIteratorFileUploadSource())
      ->setName($filename)
      ->setViewPolicy(PhabricatorPolicies::POLICY_NOONE)
      ->setMIMEType($mime_type)
      ->setRelativeTTL(phutil_units('60 minutes in seconds'))
      ->setAuthorPHID($viewer->getPHID())
      ->setIterator($iterator);

    return $source->uploadFile();
  }

}
