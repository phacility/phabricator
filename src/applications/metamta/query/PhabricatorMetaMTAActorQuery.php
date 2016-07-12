<?php

final class PhabricatorMetaMTAActorQuery extends PhabricatorQuery {

  private $phids = array();
  private $viewer;

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public function getViewer() {
    return $this->viewer;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function execute() {
    $phids = array_fuse($this->phids);
    $actors = array();
    $type_map = array();
    foreach ($phids as $phid) {
      $type_map[phid_get_type($phid)][] = $phid;
      $actors[$phid] = id(new PhabricatorMetaMTAActor())->setPHID($phid);
    }

    // TODO: Move this to PhabricatorPHIDType, or the objects, or some
    // interface.

    foreach ($type_map as $type => $phids) {
      switch ($type) {
        case PhabricatorPeopleUserPHIDType::TYPECONST:
          $this->loadUserActors($actors, $phids);
          break;
        case PhabricatorPeopleExternalPHIDType::TYPECONST:
          $this->loadExternalUserActors($actors, $phids);
          break;
        default:
          $this->loadUnknownActors($actors, $phids);
          break;
      }
    }

    return $actors;
  }

  private function loadUserActors(array $actors, array $phids) {
    assert_instances_of($actors, 'PhabricatorMetaMTAActor');

    $emails = id(new PhabricatorUserEmail())->loadAllWhere(
      'userPHID IN (%Ls) AND isPrimary = 1',
      $phids);
    $emails = mpull($emails, null, 'getUserPHID');

    $users = id(new PhabricatorPeopleQuery())
      ->setViewer($this->getViewer())
      ->withPHIDs($phids)
      ->needUserSettings(true)
      ->execute();
    $users = mpull($users, null, 'getPHID');

    foreach ($phids as $phid) {
      $actor = $actors[$phid];

      $user = idx($users, $phid);
      if (!$user) {
        $actor->setUndeliverable(PhabricatorMetaMTAActor::REASON_UNLOADABLE);
      } else {
        $actor->setName($this->getUserName($user));
        if ($user->getIsDisabled()) {
          $actor->setUndeliverable(PhabricatorMetaMTAActor::REASON_DISABLED);
        }
        if ($user->getIsSystemAgent()) {
          $actor->setUndeliverable(PhabricatorMetaMTAActor::REASON_BOT);
        }

        // NOTE: We do send email to unapproved users, and to unverified users,
        // because it would otherwise be impossible to get them to verify their
        // email addresses. Possibly we should white-list this kind of mail and
        // deny all other types of mail.
      }

      $email = idx($emails, $phid);
      if (!$email) {
        $actor->setUndeliverable(PhabricatorMetaMTAActor::REASON_NO_ADDRESS);
      } else {
        $actor->setEmailAddress($email->getAddress());
      }
    }
  }

  private function loadExternalUserActors(array $actors, array $phids) {
    assert_instances_of($actors, 'PhabricatorMetaMTAActor');

    $xusers = id(new PhabricatorExternalAccountQuery())
      ->setViewer($this->getViewer())
      ->withPHIDs($phids)
      ->execute();
    $xusers = mpull($xusers, null, 'getPHID');

    foreach ($phids as $phid) {
      $actor = $actors[$phid];

      $xuser = idx($xusers, $phid);
      if (!$xuser) {
        $actor->setUndeliverable(PhabricatorMetaMTAActor::REASON_UNLOADABLE);
        continue;
      }

      $actor->setName($xuser->getDisplayName());

      if ($xuser->getAccountType() != 'email') {
        $actor->setUndeliverable(PhabricatorMetaMTAActor::REASON_EXTERNAL_TYPE);
        continue;
      }

      $actor->setEmailAddress($xuser->getAccountID());
    }
  }


  private function loadUnknownActors(array $actors, array $phids) {
    foreach ($phids as $phid) {
      $actor = $actors[$phid];
      $actor->setUndeliverable(PhabricatorMetaMTAActor::REASON_UNMAILABLE);
    }
  }


  /**
   * Small helper function to make sure we format the username properly as
   * specified by the `metamta.user-address-format` configuration value.
   */
  private function getUserName(PhabricatorUser $user) {
    $format = PhabricatorEnv::getEnvConfig('metamta.user-address-format');

    switch ($format) {
      case 'short':
        $name = $user->getUserName();
        break;
      case 'real':
        $name = strlen($user->getRealName()) ?
          $user->getRealName() : $user->getUserName();
        break;
      case 'full':
      default:
        $name = $user->getFullName();
        break;
    }

    return $name;
  }

}
