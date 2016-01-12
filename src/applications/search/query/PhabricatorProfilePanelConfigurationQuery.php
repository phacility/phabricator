<?php

final class PhabricatorProfilePanelConfigurationQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $profileObjectPHIDs;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withProfileObjectPHIDs(array $phids) {
    $this->profileObjectPHIDs = $phids;
    return $this;
  }

  public function newResultObject() {
    return new PhabricatorProfilePanelConfiguration();
  }

  protected function loadPage() {
    return $this->loadStandardPage($this->newResultObject());
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

    if ($this->profileObjectPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'profileObjectPHID IN (%Ls)',
        $this->profileObjectPHIDs);
    }

    return $where;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorSearchApplication';
  }

}
