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
  const NAMETOKEN_TABLE = 'user_nametoken';

  protected $phid;
  protected $userName;
  protected $realName;
  protected $email;
  protected $passwordSalt;
  protected $passwordHash;
  protected $profileImagePHID;
  protected $timezoneIdentifier = '';

  protected $consoleEnabled = 0;
  protected $consoleVisible = 0;
  protected $consoleTab = '';

  protected $conduitCertificate;

  protected $isSystemAgent = 0;
  protected $isAdmin = 0;
  protected $isDisabled = 0;

  private $preferences = null;

  protected function readField($field) {
    if ($field === 'profileImagePHID') {
      return nonempty(
        $this->profileImagePHID,
        PhabricatorEnv::getEnvConfig('user.default-profile-image-phid'));
    }
    if ($field === 'timezoneIdentifier') {
      // If the user hasn't set one, guess the server's time.
      return nonempty(
        $this->timezoneIdentifier,
        date_default_timezone_get());
    }
    return parent::readField($field);
  }

  public function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_PARTIAL_OBJECTS => true,
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

  public function isLoggedIn() {
    return !($this->getPHID() === null);
  }

  public function save() {
    if (!$this->getConduitCertificate()) {
      $this->setConduitCertificate($this->generateConduitCertificate());
    }
    $result = parent::save();

    $this->updateNameTokens();
    PhabricatorSearchUserIndexer::indexUser($this);

    return $result;
  }

  private function generateConduitCertificate() {
    return Filesystem::readRandomCharacters(255);
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

    // When the user posts a form, we check that it contains a valid CSRF token.
    // Tokens cycle each hour (every CSRF_CYLCE_FREQUENCY seconds) and we accept
    // either the current token, the next token (users can submit a "future"
    // token if you have two web frontends that have some clock skew) or any of
    // the last 6 tokens. This means that pages are valid for up to 7 hours.
    // There is also some Javascript which periodically refreshes the CSRF
    // tokens on each page, so theoretically pages should be valid indefinitely.
    // However, this code may fail to run (if the user loses their internet
    // connection, or there's a JS problem, or they don't have JS enabled).
    // Choosing the size of the window in which we accept old CSRF tokens is
    // an issue of balancing concerns between security and usability. We could
    // choose a very narrow (e.g., 1-hour) window to reduce vulnerability to
    // attacks using captured CSRF tokens, but it's also more likely that real
    // users will be affected by this, e.g. if they close their laptop for an
    // hour, open it back up, and try to submit a form before the CSRF refresh
    // can kick in. Since the user experience of submitting a form with expired
    // CSRF is often quite bad (you basically lose data, or it's a big pain to
    // recover at least) and I believe we gain little additional protection
    // by keeping the window very short (the overwhelming value here is in
    // preventing blind attacks, and most attacks which can capture CSRF tokens
    // can also just capture authentication information [sniffing networks]
    // or act as the user [xss]) the 7 hour default seems like a reasonable
    // balance. Other major platforms have much longer CSRF token lifetimes,
    // like Rails (session duration) and Django (forever), which suggests this
    // is a reasonable analysis.
    $csrf_window = 6;

    for ($ii = -$csrf_window; $ii <= 1; $ii++) {
      $valid = $this->getCSRFToken($ii);
      if ($token == $valid) {
        return true;
      }
    }
    return false;
  }

  private function generateToken($epoch, $frequency, $key, $len) {
    $time_block = floor($epoch / $frequency);
    $vec = $this->getPHID().$this->getPasswordHash().$key.$time_block;
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
    $session_key = Filesystem::readRandomCharacters(40);

    // UNGUARDED WRITES: Logging-in users don't have CSRF stuff yet.
    $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();

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

  public function destroySession($session_key) {
    $conn_w = $this->establishConnection('w');
    queryfx(
      $conn_w,
      'DELETE FROM %T WHERE userPHID = %s AND sessionKey = %s',
      self::SESSION_TABLE,
      $this->getPHID(),
      $session_key);
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

  private static function tokenizeName($name) {
    if (function_exists('mb_strtolower')) {
      $name = mb_strtolower($name, 'UTF-8');
    } else {
      $name = strtolower($name);
    }
    $name = trim($name);
    if (!strlen($name)) {
      return array();
    }
    return preg_split('/\s+/', $name);
  }

  /**
   * Populate the nametoken table, which used to fetch typeahead results. When
   * a user types "linc", we want to match "Abraham Lincoln" from on-demand
   * typeahead sources. To do this, we need a separate table of name fragments.
   */
  public function updateNameTokens() {
    $tokens = array_merge(
      self::tokenizeName($this->getRealName()),
      self::tokenizeName($this->getUserName()));
    $tokens = array_unique($tokens);
    $table  = self::NAMETOKEN_TABLE;
    $conn_w = $this->establishConnection('w');

    $sql = array();
    foreach ($tokens as $token) {
      $sql[] = qsprintf(
        $conn_w,
        '(%d, %s)',
        $this->getID(),
        $token);
    }

    queryfx(
      $conn_w,
      'DELETE FROM %T WHERE userID = %d',
      $table,
      $this->getID());
    if ($sql) {
      queryfx(
        $conn_w,
        'INSERT INTO %T (userID, token) VALUES %Q',
        $table,
        implode(', ', $sql));
    }
  }

}
