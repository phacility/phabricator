<?php

final class PhortuneAccountEmailQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $accountPHIDs;
  private $addressKeys;
  private $statuses;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withAccountPHIDs(array $phids) {
    $this->accountPHIDs = $phids;
    return $this;
  }

  public function withAddressKeys(array $keys) {
    $this->addressKeys = $keys;
    return $this;
  }

  public function withStatuses(array $statuses) {
    $this->statuses = $statuses;
    return $this;
  }

  public function newResultObject() {
    return new PhortuneAccountEmail();
  }

  protected function willFilterPage(array $addresses) {
    $accounts = id(new PhortuneAccountQuery())
      ->setViewer($this->getViewer())
      ->setParentQuery($this)
      ->withPHIDs(mpull($addresses, 'getAccountPHID'))
      ->execute();
    $accounts = mpull($accounts, null, 'getPHID');

    foreach ($addresses as $key => $address) {
      $account = idx($accounts, $address->getAccountPHID());

      if (!$account) {
        $this->didRejectResult($addresses[$key]);
        unset($addresses[$key]);
        continue;
      }

      $address->attachAccount($account);
    }

    return $addresses;
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'address.id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn,
        'address.phid IN (%Ls)',
        $this->phids);
    }

    if ($this->accountPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'address.accountPHID IN (%Ls)',
        $this->accountPHIDs);
    }

    if ($this->addressKeys !== null) {
      $where[] = qsprintf(
        $conn,
        'address.addressKey IN (%Ls)',
        $this->addressKeys);
    }

    if ($this->statuses !== null) {
      $where[] = qsprintf(
        $conn,
        'address.status IN (%Ls)',
        $this->statuses);
    }

    return $where;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorPhortuneApplication';
  }

  protected function getPrimaryTableAlias() {
    return 'address';
  }

}
