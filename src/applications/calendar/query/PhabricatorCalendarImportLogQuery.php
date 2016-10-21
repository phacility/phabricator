<?php

final class PhabricatorCalendarImportLogQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $importPHIDs;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withImportPHIDs(array $phids) {
    $this->importPHIDs = $phids;
    return $this;
  }

  public function newResultObject() {
    return new PhabricatorCalendarImportLog();
  }

  protected function loadPage() {
    return $this->loadStandardPage($this->newResultObject());
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'log.id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn,
        'log.phid IN (%Ls)',
        $this->phids);
    }

    if ($this->importPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'log.importPHID IN (%Ls)',
        $this->importPHIDs);
    }


    return $where;
  }

  protected function willFilterPage(array $page) {
    $viewer = $this->getViewer();

    $type_map = PhabricatorCalendarImportLogType::getAllLogTypes();
    foreach ($page as $log) {
      $type_constant = $log->getParameter('type');

      $type_object = idx($type_map, $type_constant);
      if (!$type_object) {
        $type_object = new PhabricatorCalendarImportDefaultLogType();
      }

      $type_object = clone $type_object;
      $log->attachLogType($type_object);
    }

    $import_phids = mpull($page, 'getImportPHID');

    if ($import_phids) {
      $imports = id(new PhabricatorCalendarImportQuery())
        ->setViewer($viewer)
        ->withPHIDs($import_phids)
        ->execute();
      $imports = mpull($imports, null, 'getPHID');
    } else {
      $imports = array();
    }

    foreach ($page as $key => $log) {
      $import = idx($imports, $log->getImportPHID());
      if (!$import) {
        $this->didRejectResult($import);
        unset($page[$key]);
        continue;
      }

      $log->attachImport($import);
    }

    return $page;
  }

  protected function getPrimaryTableAlias() {
    return 'log';
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorCalendarApplication';
  }

}
