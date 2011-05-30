<?php

/*
 * Copyright 2011 Facebook, Inc.
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

class PhabricatorUser extends PhabricatorUserDAO {

  const SESSION_TABLE = 'phabricator_session';

  protected $phid;
  protected $userName;
  protected $realName;
  protected $email;
  protected $passwordSalt;
  protected $passwordHash;
  protected $profileImagePHID;

  protected $consoleEnabled = 0;
  protected $consoleVisible = 0;
  protected $consoleTab = '';

  protected $conduitCertificate;

  protected $isSystemAgent = 0;
  protected $isAdmin = 0;
  protected $isDisabled = 0;

  private $preferences = null;

  public function getProfileImagePHID() {
    return nonempty(
      $this->profileImagePHID,
      PhabricatorEnv::getEnvConfig('user.default-profile-image-phid'));
  }

  public function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorPHIDConstants::PHID_TYPE_USER);
  }

  public function setPassword($password) {
    if (!$this->getPHID()) {
      throw new Exception(
        "You can not set a password for an unsaved user because their PHID ".
        "is a salt component in the password hash.");
    }

    if (!strlen($password)) {
      $this->setPasswordHash('');
    } else {
      $this->setPasswordSalt(md5(mt_rand()));
      $hash = $this->hashPassword($password);
      $this->setPasswordHash($hash);
    }
    return $this;
  }

  public function save() {
    if (!$this->conduitCertificate) {
      $this->conduitCertificate = $this->generateConduitCertificate();
    }
    return parent::save();
  }

  private function generateConduitCertificate() {
    $entropy = Filesystem::readRandomBytes(256);
    $entropy = base64_encode($entropy);
    $entropy = substr($entropy, 0, 255);
    return $entropy;
  }

  public function comparePassword($password) {
    if (!strlen($password)) {
      return false;
    }
    if (!strlen($this->getPasswordHash())) {
      return false;
    }
    $password = $this->hashPassword($password);
    return ($password === $this->getPasswordHash());
  }

  private function hashPassword($password) {
    $password = $this->getUsername().
                $password.
                $this->getPHID().
                $this->getPasswordSalt();
    for ($ii = 0; $ii < 1000; $ii++) {
      $password = md5($password);
    }
    return $password;
  }

  const CSRF_CYCLE_FREQUENCY  = 3600;
  const CSRF_TOKEN_LENGTH     = 16;

  const EMAIL_CYCLE_FREQUENCY = 86400;
  const EMAIL_TOKEN_LENGTH    = 24;

  public function getCSRFToken($offset = 0) {
    return $this->generateToken(
      time() + (self::CSRF_CYCLE_FREQUENCY * $offset),
      self::CSRF_CYCLE_FREQUENCY,
      PhabricatorEnv::getEnvConfig('phabricator.csrf-key'),
      self::CSRF_TOKEN_LENGTH);
  }

  public function validateCSRFToken($token) {
    for ($ii = -1; $ii <= 1; $ii++) {
      $valid = $this->getCSRFToken($ii);
      if ($token == $valid) {
        return true;
      }
    }
    return false;
  }

  private function generateToken($epoch, $frequency, $key, $len) {
    $time_block = floor($epoch / $frequency);
    $vec = $this->getPHID().$this->passwordHash.$key.$time_block;
    return substr(sha1($vec), 0, $len);
  }

  /**
   * Issue a new session key to this user. Phabricator supports different
   * types of sessions (like "web" and "conduit") and each session type may
   * have multiple concurrent sessions (this allows a user to be logged in on
   * multiple browsers at the same time, for instance).
   *
   * Note that this method is transport-agnostic and does not set cookies or
   * issue other types of tokens, it ONLY generates a new session key.
   *
   * You can configure the maximum number of concurrent sessions for various
   * session types in the Phabricator configuration.
   *
   * @param   string  Session type, like "web".
   * @return  string  Newly generated session key.
   */
  public function establishSession($session_type) {
    $conn_w = $this->establishConnection('w');

    if (strpos($session_type, '-') !== false) {
      throw new Exception("Session type must not contain hyphen ('-')!");
    }

    // We allow multiple sessions of the same type, so when a caller requests
    // a new session of type "web", we give them the first available session in
    // "web-1", "web-2", ..., "web-N", up to some configurable limit. If none
    // of these sessions is available, we overwrite the oldest session and
    // reissue a new one in its place.

    $session_limit = 1;
    switch ($session_type) {
      case 'web':
        $session_limit = PhabricatorEnv::getEnvConfig('auth.sessions.web');
        break;
      case 'conduit':
        $session_limit = PhabricatorEnv::getEnvConfig('auth.sessions.conduit');
        break;
      default:
        throw new Exception("Unknown session type '{$session_type}'!");
    }

    $session_limit = (int)$session_limit;
    if ($session_limit <= 0) {
      throw new Exception(
        "Session limit for '{$session_type}' must be at least 1!");
    }

    // Load all the currently active sessions.
    $sessions = queryfx_all(
      $conn_w,
      'SELECT type, sessionStart FROM %T WHERE userPHID = %s AND type LIKE %>',
      PhabricatorUser::SESSION_TABLE,
      $this->getPHID(),
      $session_type.'-');

    // Choose which 'type' we'll actually establish, i.e. what number we're
    // going to append to the basic session type. To do this, just check all
    // the numbers sequentially until we find an available session.
    $establish_type = null;
    $sessions = ipull($sessions, null, 'type');
    for ($ii = 1; $ii <= $session_limit; $ii++) {
      if (empty($sessions[$session_type.'-'.$ii])) {
        $establish_type = $session_type.'-'.$ii;
        break;
      }
    }

    // If we didn't find an available session, choose the oldest session and
    // overwrite it.
    if (!$establish_type) {
      $sessions = isort($sessions, 'sessionStart');
      $oldest = reset($sessions);
      $establish_type = $oldest['type'];
    }

    // Consume entropy to generate a new session key, forestalling the eventual
    // heat death of the universe.
    $entropy = Filesystem::readRandomBytes(20);
    $session_key = sha1($entropy);

    queryfx(
      $conn_w,
      'INSERT INTO %T '.
        '(userPHID, type, sessionKey, sessionStart)'.
      ' VALUES '.
        '(%s, %s, %s, UNIX_TIMESTAMP()) '.
      'ON DUPLICATE KEY UPDATE '.
        'sessionKey = VALUES(sessionKey), '.
        'sessionStart = VALUES(sessionStart)',
      self::SESSION_TABLE,
      $this->getPHID(),
      $establish_type,
      $session_key);

    $log = PhabricatorUserLog::newLog(
      $this,
      $this,
      PhabricatorUserLog::ACTION_LOGIN);
    $log->setDetails(
      array(
        'session_type' => $session_type,
        'session_issued' => $establish_type,
      ));
    $log->setSession($session_key);
    $log->save();

    return $session_key;
  }

  private function generateEmailToken($offset = 0) {
    return $this->generateToken(
      time() + ($offset * self::EMAIL_CYCLE_FREQUENCY),
      self::EMAIL_CYCLE_FREQUENCY,
      PhabricatorEnv::getEnvConfig('phabricator.csrf-key').$this->getEmail(),
      self::EMAIL_TOKEN_LENGTH);
  }

  public function validateEmailToken($token) {
    for ($ii = -1; $ii <= 1; $ii++) {
      $valid = $this->generateEmailToken($ii);
      if ($token == $valid) {
        return true;
      }
    }
    return false;
  }

  public function getEmailLoginURI() {
    $token = $this->generateEmailToken();
    $uri = PhabricatorEnv::getProductionURI('/login/etoken/'.$token.'/');
    $uri = new PhutilURI($uri);
    return $uri->alter('email', $this->getEmail());
  }

  public function loadPreferences() {
    if ($this->preferences) {
      return $this->preferences;
    }

    $preferences = id(new PhabricatorUserPreferences())->loadOneWhere(
      'userPHID = %s',
      $this->getPHID());

    if (!$preferences) {
      $preferences = new PhabricatorUserPreferences();
      $preferences->setUserPHID($this->getPHID());

      $default_dict = array(
        PhabricatorUserPreferences::PREFERENCE_TITLES => 'glyph',
        PhabricatorUserPreferences::PREFERENCE_MONOSPACED => '');

      $preferences->setPreferences($default_dict);
    }

    $this->preferences = $preferences;
    return $preferences;
  }

}
