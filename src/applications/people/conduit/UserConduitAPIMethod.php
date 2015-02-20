<?php

abstract class UserConduitAPIMethod extends ConduitAPIMethod {

  final public function getApplication() {
    return PhabricatorApplication::getByClass('PhabricatorPeopleApplication');
  }

  protected function buildUserInformationDictionary(
    PhabricatorUser $user,
    PhabricatorCalendarEvent $current_status = null) {

    $roles = array();
    if ($user->getIsDisabled()) {
      $roles[] = 'disabled';
    }
    if ($user->getIsSystemAgent()) {
      $roles[] = 'agent';
    }
    if ($user->getIsAdmin()) {
      $roles[] = 'admin';
    }

    $primary = $user->loadPrimaryEmail();
    if ($primary && $primary->getIsVerified()) {
      $email = $primary->getAddress();
      $roles[] = 'verified';
    } else {
      $email = null;
      $roles[] = 'unverified';
    }

    if ($user->getIsApproved()) {
      $roles[] = 'approved';
    }

    if ($user->isUserActivated()) {
      $roles[] = 'activated';
    }

    $return = array(
      'phid'         => $user->getPHID(),
      'userName'     => $user->getUserName(),
      'realName'     => $user->getRealName(),
      'primaryEmail' => $email,
      'image'        => $user->getProfileImageURI(),
      'uri'          => PhabricatorEnv::getURI('/p/'.$user->getUsername().'/'),
      'roles'        => $roles,
    );

    if ($current_status) {
      $return['currentStatus'] = $current_status->getTextStatus();
      $return['currentStatusUntil'] = $current_status->getDateTo();
    }

    return $return;
  }

}
