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

final class PhabricatorUserLog extends PhabricatorUserDAO {

  const ACTION_LOGIN          = 'login';
  const ACTION_LOGOUT         = 'logout';
  const ACTION_LOGIN_FAILURE  = 'login-fail';
  const ACTION_RESET_PASSWORD = 'reset-pass';

  const ACTION_CREATE         = 'create';
  const ACTION_EDIT           = 'edit';

  const ACTION_ADMIN          = 'admin';
  const ACTION_DISABLE        = 'disable';
  const ACTION_DELETE         = 'delete';

  const ACTION_CONDUIT_CERTIFICATE = 'conduit-cert';
  const ACTION_CONDUIT_CERTIFICATE_FAILURE = 'conduit-cert-fail';

  const ACTION_EMAIL_PRIMARY    = 'email-primary';
  const ACTION_EMAIL_REMOVE     = 'email-remove';
  const ACTION_EMAIL_ADD        = 'email-add';

  const ACTION_CHANGE_PASSWORD  = 'change-password';
  const ACTION_CHANGE_USERNAME  = 'change-username';

  protected $actorPHID;
  protected $userPHID;
  protected $action;
  protected $oldValue;
  protected $newValue;
  protected $details = array();
  protected $remoteAddr;
  protected $session;

  public static function newLog(
    PhabricatorUser $actor = null,
    PhabricatorUser $user = null,
    $action) {

    $log = new PhabricatorUserLog();

    if ($actor) {
      $log->setActorPHID($actor->getPHID());
    }

    if ($user) {
      $log->setUserPHID($user->getPHID());
    } else {
      $log->setUserPHID('');
    }

    if ($action) {
      $log->setAction($action);
    }

    return $log;
  }

  public static function loadRecentEventsFromThisIP($action, $timespan) {
    return id(new PhabricatorUserLog())->loadAllWhere(
      'action = %s AND remoteAddr = %s AND dateCreated > %d
        ORDER BY dateCreated DESC',
      $action,
      idx($_SERVER, 'REMOTE_ADDR'),
      time() - $timespan);
  }

  public function save() {
    if (!$this->remoteAddr) {
      $this->remoteAddr = idx($_SERVER, 'REMOTE_ADDR', '');
    }
    if (!$this->session) {
      $this->setSession(idx($_COOKIE, 'phsid'));
    }
    $this->details['host'] = php_uname('n');
    $this->details['user_agent'] = idx($_SERVER, 'HTTP_USER_AGENT');

    return parent::save();
  }

  public function setSession($session) {
    // Store the hash of the session, not the actual session key, so that
    // seeing the logs doesn't compromise all the sessions which appear in
    // them. This just prevents casual leaks, like in a screenshot.
    if (strlen($session)) {
      $this->session = PhabricatorHash::digest($session);
    }
    return $this;
  }

  public function getConfiguration() {
    return array(
      self::CONFIG_SERIALIZATION => array(
        'oldValue' => self::SERIALIZATION_JSON,
        'newValue' => self::SERIALIZATION_JSON,
        'details'  => self::SERIALIZATION_JSON,
      ),
    ) + parent::getConfiguration();
  }

}
