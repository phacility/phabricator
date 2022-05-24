<?php

final class PhabricatorWorkerBulkJobQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $authorPHIDs;
  private $bulkJobTypes;
  private $statuses;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withAuthorPHIDs(array $author_phids) {
    $this->authorPHIDs = $author_phids;
    return $this;
  }

  public function withBulkJobTypes(array $job_types) {
    $this->bulkJobTypes = $job_types;
    return $this;
  }

  public function withStatuses(array $statuses) {
    $this->statuses = $statuses;
    return $this;
  }

  public function newResultObject() {
    return new PhabricatorWorkerBulkJob();
  }

  protected function willFilterPage(array $page) {
    $map = PhabricatorWorkerBulkJobType::getAllJobTypes();

    foreach ($page as $key => $job) {
      $implementation = idx($map, $job->getJobTypeKey());
      if (!$implementation) {
        $this->didRejectResult($job);
        unset($page[$key]);
        continue;
      }
      $job->attachJobImplementation($implementation);
    }

    return $page;
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn,
        'phid IN (%Ls)',
        $this->phids);
    }

    if ($this->authorPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'authorPHID IN (%Ls)',
        $this->authorPHIDs);
    }

    if ($this->bulkJobTypes !== null) {
      $where[] = qsprintf(
        $conn,
        'bulkJobType IN (%Ls)',
        $this->bulkJobTypes);
    }

    if ($this->statuses !== null) {
      $where[] = qsprintf(
        $conn,
        'status IN (%Ls)',
        $this->statuses);
    }

    return $where;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorDaemonsApplication';
  }

}
