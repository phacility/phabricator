<?php

final class PhabricatorRepositoryIdentityQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $identityNames;
  private $emailAddress;
  private $assigneePHIDs;
  private $identityNameLike;
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

  public function withIdentityNameLike($name_like) {
    $this->identityNameLike = $name_like;
    return $this;
  }

  public function withEmailAddress($address) {
    $this->emailAddress = $address;
    return $this;
  }

  public function withAssigneePHIDs(array $assignees) {
    $this->assigneePHIDs = $assignees;
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

    if ($this->assigneePHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'repository_identity.currentEffectiveUserPHID IN (%Ls)',
        $this->assigneePHIDs);
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

    if ($this->emailAddress !== null) {
      $identity_style = "<{$this->emailAddress}>";
      $where[] = qsprintf(
        $conn,
        'repository_identity.identityNameRaw LIKE %<',
        $identity_style);
    }

    if ($this->identityNameLike != null) {
      $where[] = qsprintf(
        $conn,
        'repository_identity.identityNameRaw LIKE %~',
        $this->identityNameLike);
    }

    return $where;
  }

  protected function didFilterPage(array $identities) {
    $user_ids = array_filter(
      mpull($identities, 'getCurrentEffectiveUserPHID', 'getID'));
    if (!$user_ids) {
      return $identities;
    }

    $users = id(new PhabricatorPeopleQuery())
      ->withPHIDs($user_ids)
      ->setViewer($this->getViewer())
      ->execute();
    $users = mpull($users, null, 'getPHID');

    foreach ($identities as $identity) {
      if ($identity->hasEffectiveUser()) {
        $user = idx($users, $identity->getCurrentEffectiveUserPHID());
        $identity->attachEffectiveUser($user);
      }
    }

    return $identities;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorDiffusionApplication';
  }

}
