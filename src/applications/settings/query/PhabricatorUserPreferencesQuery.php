<?php

final class PhabricatorUserPreferencesQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $userPHIDs;
  private $builtinKeys;
  private $hasUserPHID;
  private $users = array();
  private $synthetic;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withHasUserPHID($is_user) {
    $this->hasUserPHID = $is_user;
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

  public function withBuiltinKeys(array $keys) {
    $this->builtinKeys = $keys;
    return $this;
  }

  /**
   * Always return preferences for every queried user.
   *
   * If no settings exist for a user, a new empty settings object with
   * appropriate defaults is returned.
   *
   * @param bool True to generat synthetic preferences for missing users.
   */
  public function needSyntheticPreferences($synthetic) {
    $this->synthetic = $synthetic;
    return $this;
  }

  public function newResultObject() {
    return new PhabricatorUserPreferences();
  }

  protected function loadPage() {
    $preferences = $this->loadStandardPage($this->newResultObject());

    if ($this->synthetic) {
      $user_map = mpull($preferences, null, 'getUserPHID');
      foreach ($this->userPHIDs as $user_phid) {
        if (isset($user_map[$user_phid])) {
          continue;
        }
        $preferences[] = $this->newResultObject()
          ->setUserPHID($user_phid);
      }
    }

    return $preferences;
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

    $need_global = array();
    foreach ($prefs as $key => $pref) {
      $user_phid = $pref->getUserPHID();
      if (!$user_phid) {
        $pref->attachUser(null);
        continue;
      }

      $need_global[] = $pref;

      $user = idx($users, $user_phid);
      if (!$user) {
        $this->didRejectResult($pref);
        unset($prefs[$key]);
        continue;
      }

      $pref->attachUser($user);
    }

    // If we loaded any user preferences, load the global defaults and attach
    // them if they exist.
    if ($need_global) {
      $global = id(new self())
        ->setViewer($this->getViewer())
        ->withBuiltinKeys(
          array(
            PhabricatorUserPreferences::BUILTIN_GLOBAL_DEFAULT,
          ))
        ->executeOne();
      if ($global) {
        foreach ($need_global as $pref) {
          $pref->attachDefaultSettings($global);
        }
      }
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

    if ($this->builtinKeys !== null) {
      $where[] = qsprintf(
        $conn,
        'builtinKey IN (%Ls)',
        $this->builtinKeys);
    }

    if ($this->hasUserPHID !== null) {
      if ($this->hasUserPHID) {
        $where[] = qsprintf(
          $conn,
          'userPHID IS NOT NULL');
      } else {
        $where[] = qsprintf(
          $conn,
          'userPHID IS NULL');
      }
    }

    return $where;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorSettingsApplication';
  }

}
