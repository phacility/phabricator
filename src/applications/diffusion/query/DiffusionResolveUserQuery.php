<?php

/**
 * Resolve an author or committer name, like
 * `"Abraham Lincoln <alincoln@logcab.in>"`, into a valid Phabricator user
 * account, like `@alincoln`.
 */
final class DiffusionResolveUserQuery extends Phobject {

  private $name;
  private $commit;

  public function withName($name) {
    $this->name = $name;
    return $this;
  }

  public function withCommit($commit) {
    $this->commit = $commit;
    return $this;
  }

  public function execute() {
    $user_name = $this->name;

    $phid = $this->findUserPHID($this->name);
    $phid = $this->fireLookupEvent($phid);

    return $phid;
  }

  private function findUserPHID($user_name) {
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


  /**
   * Emit an event so installs can do custom lookup of commit authors who may
   * not be naturally resolvable.
   */
  private function fireLookupEvent($guess) {

    $type = PhabricatorEventType::TYPE_DIFFUSION_LOOKUPUSER;
    $data = array(
      'commit'  => $this->commit,
      'query'   => $this->name,
      'result'  => $guess,
    );

    $event = new PhabricatorEvent($type, $data);
    PhutilEventEngine::dispatchEvent($event);

    return $event->getValue('result');
  }


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
