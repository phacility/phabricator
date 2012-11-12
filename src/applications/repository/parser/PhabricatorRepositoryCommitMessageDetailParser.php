<?php

abstract class PhabricatorRepositoryCommitMessageDetailParser {

  private $commit;
  private $commitData;

  final public function __construct(
    PhabricatorRepositoryCommit $commit,
    PhabricatorRepositoryCommitData $data) {
    $this->commit = $commit;
    $this->commitData = $data;
  }

  final public function getCommit() {
    return $this->commit;
  }

  final public function getCommitData() {
    return $this->commitData;
  }

  /**
   * Try to link a commit name to a Phabricator account. Basically we throw it
   * at the wall and see if something sticks.
   */
  public function resolveUserPHID($user_name) {
    if (!strlen($user_name)) {
      return null;
    }

    $phid = $this->findUserByUserName($user_name);
    if ($phid) {
      return $phid;
    }
    $phid = $this->findUserByEmailAddress($user_name);
    if ($phid) {
      return $phid;
    }
    $phid = $this->findUserByRealName($user_name);
    if ($phid) {
      return $phid;
    }

    // No hits yet, try to parse it as an email address.

    $email = new PhutilEmailAddress($user_name);

    $phid = $this->findUserByEmailAddress($email->getAddress());
    if ($phid) {
      return $phid;
    }

    $display_name = $email->getDisplayName();
    if ($display_name) {
      $phid = $this->findUserByUserName($display_name);
      if ($phid) {
        return $phid;
      }
      $phid = $this->findUserByRealName($display_name);
      if ($phid) {
        return $phid;
      }
    }

    return null;
  }

  abstract public function parseCommitDetails();

  private function findUserByUserName($user_name) {
    $by_username = id(new PhabricatorUser())->loadOneWhere(
      'userName = %s',
      $user_name);
    if ($by_username) {
      return $by_username->getPHID();
    }
    return null;
  }

  private function findUserByRealName($real_name) {
    // Note, real names are not guaranteed unique, which is why we do it this
    // way.
    $by_realname = id(new PhabricatorUser())->loadAllWhere(
      'realName = %s',
      $real_name);
    if (count($by_realname) == 1) {
      return reset($by_realname)->getPHID();
    }
    return null;
  }

  private function findUserByEmailAddress($email_address) {
    $by_email = PhabricatorUser::loadOneWithEmailAddress($email_address);
    if ($by_email) {
      return $by_email->getPHID();
    }
    return null;
  }

}
