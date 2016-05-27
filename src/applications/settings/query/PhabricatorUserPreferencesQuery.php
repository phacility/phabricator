<?php

final class PhabricatorUserPreferencesQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $userPHIDs;
  private $users = array();

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withUserPHIDs(array $phids) {
    $this->userPHIDs = $phids;
    return $this;
  }

  public function withUsers(array $users) {
    assert_instances_of($users, 'PhabricatorUser');
    $this->users = mpull($users, null, 'getPHID');
    $this->withUserPHIDs(array_keys($this->users));
    return $this;
  }

  public function newResultObject() {
    return new PhabricatorUserPreferences();
  }

  protected function loadPage() {
    return $this->loadStandardPage($this->newResultObject());
  }

  protected function willFilterPage(array $prefs) {
    $user_phids = mpull($prefs, 'getUserPHID');
    $user_phids = array_filter($user_phids);

    // If some of the preferences are attached to users, try to use any objects
    // we were handed first. If we're missing some, load them.

    if ($user_phids) {
      $users = $this->users;

      $user_phids = array_fuse($user_phids);
      $load_phids = array_diff_key($user_phids, $users);
      $load_phids = array_keys($load_phids);

      if ($load_phids) {
        $load_users = id(new PhabricatorPeopleQuery())
          ->setViewer($this->getViewer())
          ->withPHIDs($load_phids)
          ->execute();
        $load_users = mpull($load_users, null, 'getPHID');
        $users += $load_users;
      }
    } else {
      $users = array();
    }

    foreach ($prefs as $key => $pref) {
      $user_phid = $pref->getUserPHID();
      if (!$user_phid) {
        $pref->attachUser(null);
        continue;
      }

      $user = idx($users, $user_phid);
      if (!$user) {
        $this->didRejectResult($pref);
        unset($prefs[$key]);
        continue;
      }

      $pref->attachUser($user);
    }

    return $prefs;
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

    if ($this->userPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'userPHID IN (%Ls)',
        $this->userPHIDs);
    }

    return $where;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorSettingsApplication';
  }

}
