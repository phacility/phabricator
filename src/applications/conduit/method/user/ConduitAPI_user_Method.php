<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * @group conduit
 */
abstract class ConduitAPI_user_Method extends ConduitAPIMethod {

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
