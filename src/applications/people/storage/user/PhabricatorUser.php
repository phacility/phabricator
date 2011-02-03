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

  const PHID_TYPE = 'USER';

  protected $phid;
  protected $userName;
  protected $realName;
  protected $email;
  protected $passwordSalt;
  protected $passwordHash;
  protected $facebookUID;
  protected $profileImagePHID;

  protected $consoleEnabled = 0;
  protected $consoleVisible = 0;
  protected $consoleTab = '';

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
    return PhabricatorPHID::generateNewPHID(self::PHID_TYPE);
  }

  public function setPassword($password) {
    $this->setPasswordSalt(md5(mt_rand()));
    $hash = $this->hashPassword($password);
    $this->setPasswordHash($hash);
    return $this;
  }

  public function comparePassword($password) {
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

  public function establishSession($session_type) {
    $conn_w = $this->establishConnection('w');

    $urandom = fopen('/dev/urandom', 'r');
    if (!$urandom) {
      throw new Exception("Failed to open /dev/urandom!");
    }

    $entropy = fread($urandom, 20);
    if (strlen($entropy) != 20) {
      throw new Exception("Failed to read /dev/urandom!");
    }

    $session_key = sha1($entropy);
    queryfx(
      $conn_w,
      'INSERT INTO phabricator_session '.
        '(userPHID, type, sessionKey, sessionStart)'.
      ' VALUES '.
        '(%s, %s, %s, UNIX_TIMESTAMP()) '.
      'ON DUPLICATE KEY UPDATE '.
        'sessionKey = VALUES(sessionKey), '.
        'sessionStart = VALUES(sessionStart)',
      $this->getPHID(),
      $session_type,
      $session_key);

    $this->sessionKey = $session_key;

    return $session_key;
  }

  public function generateEmailToken($offset = 0) {
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

}
