<?php

final class PhabricatorRepositoryIdentityQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $identityNames;
  private $hasEffectivePHID;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withIdentityNames(array $names) {
    $this->identityNames = $names;
    return $this;
  }

  public function withHasEffectivePHID($has_effective_phid) {
    $this->hasEffectivePHID = $has_effective_phid;
    return $this;
  }

  public function newResultObject() {
    return new PhabricatorRepositoryIdentity();
  }

  protected function getPrimaryTableAlias() {
     return 'repository_identity';
   }

  protected function loadPage() {
    return $this->loadStandardPage($this->newResultObject());
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'repository_identity.id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn,
        'repository_identity.phid IN (%Ls)',
        $this->phids);
    }

    if ($this->hasEffectivePHID !== null) {

      if ($this->hasEffectivePHID) {
        $where[] = qsprintf(
          $conn,
          'repository_identity.currentEffectiveUserPHID IS NOT NULL');
      } else {
        $where[] = qsprintf(
          $conn,
          'repository_identity.currentEffectiveUserPHID IS NULL');
      }
    }

    if ($this->identityNames !== null) {
      $name_hashes = array();
      foreach ($this->identityNames as $name) {
        $name_hashes[] = PhabricatorHash::digestForIndex($name);
      }

      $where[] = qsprintf(
        $conn,
        'repository_identity.identityNameHash IN (%Ls)',
        $name_hashes);
    }

    return $where;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorDiffusionApplication';
  }

}
