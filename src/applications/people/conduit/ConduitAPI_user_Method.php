<?php

/**
 * @group conduit
 */
abstract class ConduitAPI_user_Method extends ConduitAPIMethod {

  public function getApplication() {
    return PhabricatorApplication::getByClass(
      'PhabricatorApplicationPeople');
  }

  protected function buildUserInformationDictionary(
    PhabricatorUser $user,
    PhabricatorUserStatus $current_status = null) {

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
      $roles[] = 'verified';
    } else {
      $roles[] = 'unverified';
    }

    $return = array(
      'phid'      => $user->getPHID(),
      'userName'  => $user->getUserName(),
      'realName'  => $user->getRealName(),
      'image'     => $user->loadProfileImageURI(),
      'uri'       => PhabricatorEnv::getURI('/p/'.$user->getUsername().'/'),
      'roles'     => $roles,
    );

    if ($current_status) {
      $return['currentStatus'] = $current_status->getTextStatus();
      $return['currentStatusUntil'] = $current_status->getDateTo();
    }

    return $return;
  }

}
