<?php

final class PhabricatorPeopleUserEmailQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function newResultObject() {
    return new PhabricatorUserEmail();
  }

  protected function getPrimaryTableAlias() {
    return 'email';
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'email.id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn,
        'email.phid IN (%Ls)',
        $this->phids);
    }

    return $where;
  }

  protected function willLoadPage(array $page) {

    $user_phids = mpull($page, 'getUserPHID');

    $users = id(new PhabricatorPeopleQuery())
      ->setViewer($this->getViewer())
      ->setParentQuery($this)
      ->withPHIDs($user_phids)
      ->execute();
    $users = mpull($users, null, 'getPHID');

    foreach ($page as $key => $address) {
      $user = idx($users, $address->getUserPHID());

      if (!$user) {
        unset($page[$key]);
        $this->didRejectResult($address);
        continue;
      }

      $address->attachUser($user);
    }

    return $page;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorPeopleApplication';
  }

}
