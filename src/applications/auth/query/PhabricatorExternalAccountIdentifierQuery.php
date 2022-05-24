<?php

final class PhabricatorExternalAccountIdentifierQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $providerConfigPHIDs;
  private $externalAccountPHIDs;
  private $rawIdentifiers;

  public function withIDs($ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withProviderConfigPHIDs(array $phids) {
    $this->providerConfigPHIDs = $phids;
    return $this;
  }

  public function withExternalAccountPHIDs(array $phids) {
    $this->externalAccountPHIDs = $phids;
    return $this;
  }

  public function withRawIdentifiers(array $identifiers) {
    $this->rawIdentifiers = $identifiers;
    return $this;
  }

  public function newResultObject() {
    return new PhabricatorExternalAccountIdentifier();
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

    if ($this->providerConfigPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'providerConfigPHID IN (%Ls)',
        $this->providerConfigPHIDs);
    }

    if ($this->externalAccountPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'externalAccountPHID IN (%Ls)',
        $this->externalAccountPHIDs);
    }

    if ($this->rawIdentifiers !== null) {
      $hashes = array();
      foreach ($this->rawIdentifiers as $raw_identifier) {
        $hashes[] = PhabricatorHash::digestForIndex($raw_identifier);
      }
      $where[] = qsprintf(
        $conn,
        'identifierHash IN (%Ls)',
        $hashes);
    }

    return $where;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorPeopleApplication';
  }

}
