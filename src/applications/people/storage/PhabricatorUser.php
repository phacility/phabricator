<?php

/**
 * @task availability Availability
 * @task image-cache Profile Image Cache
 * @task factors Multi-Factor Authentication
 * @task handles Managing Handles
 */
final class PhabricatorUser
  extends PhabricatorUserDAO
  implements
    PhutilPerson,
    PhabricatorPolicyInterface,
    PhabricatorCustomFieldInterface,
    PhabricatorDestructibleInterface,
    PhabricatorSSHPublicKeyInterface,
    PhabricatorFlaggableInterface,
    PhabricatorApplicationTransactionInterface {

  const SESSION_TABLE = 'phabricator_session';
  const NAMETOKEN_TABLE = 'user_nametoken';
  const MAXIMUM_USERNAME_LENGTH = 64;

  protected $userName;
  protected $realName;
  protected $sex;
  protected $translation;
  protected $passwordSalt;
  protected $passwordHash;
  protected $profileImagePHID;
  protected $profileImageCache;
  protected $availabilityCache;
  protected $availabilityCacheTTL;
  protected $timezoneIdentifier = '';

  protected $consoleEnabled = 0;
  protected $consoleVisible = 0;
  protected $consoleTab = '';

  protected $conduitCertificate;

  protected $isSystemAgent = 0;
  protected $isMailingList = 0;
  protected $isAdmin = 0;
  protected $isDisabled = 0;
  protected $isEmailVerified = 0;
  protected $isApproved = 0;
  protected $isEnrolledInMultiFactor = 0;

  protected $accountSecret;

  private $profileImage = self::ATTACHABLE;
  private $profile = null;
  private $availability = self::ATTACHABLE;
  private $preferences = null;
  private $omnipotent = false;
  private $customFields = self::ATTACHABLE;
  private $badgePHIDs = self::ATTACHABLE;

  private $alternateCSRFString = self::ATTACHABLE;
  private $session = self::ATTACHABLE;

  private $authorities = array();
  private $handlePool;
  private $csrfSalt;

  protected function readField($field) {
    switch ($field) {
      case 'timezoneIdentifier':
        // If the user hasn't set one, guess the server's time.
        return nonempty(
          $this->timezoneIdentifier,
          date_default_timezone_get());
      // Make sure these return booleans.
      case 'isAdmin':
        return (bool)$this->isAdmin;
      case 'isDisabled':
        return (bool)$this->isDisabled;
      case 'isSystemAgent':
        return (bool)$this->isSystemAgent;
      case 'isMailingList':
        return (bool)$this->isMailingList;
      case 'isEmailVerified':
        return (bool)$this->isEmailVerified;
      case 'isApproved':
        return (bool)$this->isApproved;
      default:
        return parent::readField($field);
    }
  }


  /**
   * Is this a live account which has passed required approvals? Returns true
   * if this is an enabled, verified (if required), approved (if required)
   * account, and false otherwise.
   *
   * @return bool True if this is a standard, usable account.
   */
  public function isUserActivated() {
    if ($this->isOmnipotent()) {
      return true;
    }

    if ($this->getIsDisabled()) {
      return false;
    }

    if (!$this->getIsApproved()) {
      return false;
    }

    if (PhabricatorUserEmail::isEmailVerificationRequired()) {
      if (!$this->getIsEmailVerified()) {
        return false;
      }
    }

    return true;
  }

  public function canEstablishWebSessions() {
    if ($this->getIsMailingList()) {
      return false;
    }

    if ($this->getIsSystemAgent()) {
      return false;
    }

    return true;
  }

  public function canEstablishAPISessions() {
    if (!$this->isUserActivated()) {
      return false;
    }

    if ($this->getIsMailingList()) {
      return false;
    }

    return true;
  }

  public function canEstablishSSHSessions() {
    if (!$this->isUserActivated()) {
      return false;
    }

    if ($this->getIsMailingList()) {
      return false;
    }

    return true;
  }

  /**
   * Returns `true` if this is a standard user who is logged in. Returns `false`
   * for logged out, anonymous, or external users.
   *
   * @return bool `true` if the user is a standard user who is logged in with
   *              a normal session.
   */
  public function getIsStandardUser() {
    $type_user = PhabricatorPeopleUserPHIDType::TYPECONST;
    return $this->getPHID() && (phid_get_type($this->getPHID()) == $type_user);
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_COLUMN_SCHEMA => array(
        'userName' => 'sort64',
        'realName' => 'text128',
        'sex' => 'text4?',
        'translation' => 'text64?',
        'passwordSalt' => 'text32?',
        'passwordHash' => 'text128?',
        'profileImagePHID' => 'phid?',
        'consoleEnabled' => 'bool',
        'consoleVisible' => 'bool',
        'consoleTab' => 'text64',
        'conduitCertificate' => 'text255',
        'isSystemAgent' => 'bool',
        'isMailingList' => 'bool',
        'isDisabled' => 'bool',
        'isAdmin' => 'bool',
        'timezoneIdentifier' => 'text255',
        'isEmailVerified' => 'uint32',
        'isApproved' => 'uint32',
        'accountSecret' => 'bytes64',
        'isEnrolledInMultiFactor' => 'bool',
        'profileImageCache' => 'text255?',
        'availabilityCache' => 'text255?',
        'availabilityCacheTTL' => 'uint32?',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_phid' => null,
        'phid' => array(
          'columns' => array('phid'),
          'unique' => true,
        ),
        'userName' => array(
          'columns' => array('userName'),
          'unique' => true,
        ),
        'realName' => array(
          'columns' => array('realName'),
        ),
        'key_approved' => array(
          'columns' => array('isApproved'),
        ),
      ),
      self::CONFIG_NO_MUTATE => array(
        'profileImageCache' => true,
        'availabilityCache' => true,
        'availabilityCacheTTL' => true,
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorPeopleUserPHIDType::TYPECONST);
  }

  public function setPassword(PhutilOpaqueEnvelope $envelope) {
    if (!$this->getPHID()) {
      throw new Exception(
        pht(
          'You can not set a password for an unsaved user because their PHID '.
          'is a salt component in the password hash.'));
    }

    if (!strlen($envelope->openEnvelope())) {
      $this->setPasswordHash('');
    } else {
      $this->setPasswordSalt(md5(Filesystem::readRandomBytes(32)));
      $hash = $this->hashPassword($envelope);
      $this->setPasswordHash($hash->openEnvelope());
    }
    return $this;
  }

  // To satisfy PhutilPerson.
  public function getSex() {
    return $this->sex;
  }

  public function getMonogram() {
    return '@'.$this->getUsername();
  }

  public function isLoggedIn() {
    return !($this->getPHID() === null);
  }

  public function save() {
    if (!$this->getConduitCertificate()) {
      $this->setConduitCertificate($this->generateConduitCertificate());
    }

    if (!strlen($this->getAccountSecret())) {
      $this->setAccountSecret(Filesystem::readRandomCharacters(64));
    }

    $result = parent::save();

    if ($this->profile) {
      $this->profile->save();
    }

    $this->updateNameTokens();

    id(new PhabricatorSearchIndexer())
      ->queueDocumentForIndexing($this->getPHID());

    return $result;
  }

  public function attachSession(PhabricatorAuthSession $session) {
    $this->session = $session;
    return $this;
  }

  public function getSession() {
    return $this->assertAttached($this->session);
  }

  public function hasSession() {
    return ($this->session !== self::ATTACHABLE);
  }

  private function generateConduitCertificate() {
    return Filesystem::readRandomCharacters(255);
  }

  public function comparePassword(PhutilOpaqueEnvelope $envelope) {
    if (!strlen($envelope->openEnvelope())) {
      return false;
    }
    if (!strlen($this->getPasswordHash())) {
      return false;
    }

    return PhabricatorPasswordHasher::comparePassword(
      $this->getPasswordHashInput($envelope),
      new PhutilOpaqueEnvelope($this->getPasswordHash()));
  }

  private function getPasswordHashInput(PhutilOpaqueEnvelope $password) {
    $input =
      $this->getUsername().
      $password->openEnvelope().
      $this->getPHID().
      $this->getPasswordSalt();

    return new PhutilOpaqueEnvelope($input);
  }

  private function hashPassword(PhutilOpaqueEnvelope $password) {
    $hasher = PhabricatorPasswordHasher::getBestHasher();

    $input_envelope = $this->getPasswordHashInput($password);
    return $hasher->getPasswordHashForStorage($input_envelope);
  }

  const CSRF_CYCLE_FREQUENCY  = 3600;
  const CSRF_SALT_LENGTH      = 8;
  const CSRF_TOKEN_LENGTH     = 16;
  const CSRF_BREACH_PREFIX    = 'B@';

  const EMAIL_CYCLE_FREQUENCY = 86400;
  const EMAIL_TOKEN_LENGTH    = 24;

  private function getRawCSRFToken($offset = 0) {
    return $this->generateToken(
      time() + (self::CSRF_CYCLE_FREQUENCY * $offset),
      self::CSRF_CYCLE_FREQUENCY,
      PhabricatorEnv::getEnvConfig('phabricator.csrf-key'),
      self::CSRF_TOKEN_LENGTH);
  }

  public function getCSRFToken() {
    if ($this->isOmnipotent()) {
      // We may end up here when called from the daemons. The omnipotent user
      // has no meaningful CSRF token, so just return `null`.
      return null;
    }

    if ($this->csrfSalt === null) {
      $this->csrfSalt = Filesystem::readRandomCharacters(
        self::CSRF_SALT_LENGTH);
    }

    $salt = $this->csrfSalt;

    // Generate a token hash to mitigate BREACH attacks against SSL. See
    // discussion in T3684.
    $token = $this->getRawCSRFToken();
    $hash = PhabricatorHash::digest($token, $salt);
    return self::CSRF_BREACH_PREFIX.$salt.substr(
        $hash, 0, self::CSRF_TOKEN_LENGTH);
  }

  public function validateCSRFToken($token) {
    // We expect a BREACH-mitigating token. See T3684.
    $breach_prefix = self::CSRF_BREACH_PREFIX;
    $breach_prelen = strlen($breach_prefix);
    if (strncmp($token, $breach_prefix, $breach_prelen) !== 0) {
      return false;
    }

    $salt = substr($token, $breach_prelen, self::CSRF_SALT_LENGTH);
    $token = substr($token, $breach_prelen + self::CSRF_SALT_LENGTH);

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
      $valid = $this->getRawCSRFToken($ii);

      $digest = PhabricatorHash::digest($valid, $salt);
      $digest = substr($digest, 0, self::CSRF_TOKEN_LENGTH);
      if (phutil_hashes_are_identical($digest, $token)) {
        return true;
      }
    }

    return false;
  }

  private function generateToken($epoch, $frequency, $key, $len) {
    if ($this->getPHID()) {
      $vec = $this->getPHID().$this->getAccountSecret();
    } else {
      $vec = $this->getAlternateCSRFString();
    }

    if ($this->hasSession()) {
      $vec = $vec.$this->getSession()->getSessionKey();
    }

    $time_block = floor($epoch / $frequency);
    $vec = $vec.$key.$time_block;

    return substr(PhabricatorHash::digest($vec), 0, $len);
  }

  public function getUserProfile() {
    return $this->assertAttached($this->profile);
  }

  public function attachUserProfile(PhabricatorUserProfile $profile) {
    $this->profile = $profile;
    return $this;
  }

  public function loadUserProfile() {
    if ($this->profile) {
      return $this->profile;
    }

    $profile_dao = new PhabricatorUserProfile();
    $this->profile = $profile_dao->loadOneWhere('userPHID = %s',
      $this->getPHID());

    if (!$this->profile) {
      $profile_dao->setUserPHID($this->getPHID());
      $this->profile = $profile_dao;
    }

    return $this->profile;
  }

  public function loadPrimaryEmailAddress() {
    $email = $this->loadPrimaryEmail();
    if (!$email) {
      throw new Exception(pht('User has no primary email address!'));
    }
    return $email->getAddress();
  }

  public function loadPrimaryEmail() {
    return $this->loadOneRelative(
      new PhabricatorUserEmail(),
      'userPHID',
      'getPHID',
      '(isPrimary = 1)');
  }

  public function loadPreferences() {
    if ($this->preferences) {
      return $this->preferences;
    }

    $preferences = null;
    if ($this->getPHID()) {
      $preferences = id(new PhabricatorUserPreferences())->loadOneWhere(
        'userPHID = %s',
        $this->getPHID());
    }

    if (!$preferences) {
      $preferences = new PhabricatorUserPreferences();
      $preferences->setUserPHID($this->getPHID());

      $default_dict = array(
        PhabricatorUserPreferences::PREFERENCE_TITLES => 'glyph',
        PhabricatorUserPreferences::PREFERENCE_EDITOR => '',
        PhabricatorUserPreferences::PREFERENCE_MONOSPACED => '',
        PhabricatorUserPreferences::PREFERENCE_DARK_CONSOLE => 0,
      );

      $preferences->setPreferences($default_dict);
    }

    $this->preferences = $preferences;
    return $preferences;
  }

  public function loadEditorLink($path, $line, $callsign) {
    $editor = $this->loadPreferences()->getPreference(
      PhabricatorUserPreferences::PREFERENCE_EDITOR);

    if (is_array($path)) {
      $multiedit = $this->loadPreferences()->getPreference(
        PhabricatorUserPreferences::PREFERENCE_MULTIEDIT);
      switch ($multiedit) {
        case '':
          $path = implode(' ', $path);
          break;
        case 'disable':
          return null;
      }
    }

    if (!strlen($editor)) {
      return null;
    }

    $uri = strtr($editor, array(
      '%%' => '%',
      '%f' => phutil_escape_uri($path),
      '%l' => phutil_escape_uri($line),
      '%r' => phutil_escape_uri($callsign),
    ));

    // The resulting URI must have an allowed protocol. Otherwise, we'll return
    // a link to an error page explaining the misconfiguration.

    $ok = PhabricatorHelpEditorProtocolController::hasAllowedProtocol($uri);
    if (!$ok) {
      return '/help/editorprotocol/';
    }

    return (string)$uri;
  }

  public function getAlternateCSRFString() {
    return $this->assertAttached($this->alternateCSRFString);
  }

  public function attachAlternateCSRFString($string) {
    $this->alternateCSRFString = $string;
    return $this;
  }

  /**
   * Populate the nametoken table, which used to fetch typeahead results. When
   * a user types "linc", we want to match "Abraham Lincoln" from on-demand
   * typeahead sources. To do this, we need a separate table of name fragments.
   */
  public function updateNameTokens() {
    $table  = self::NAMETOKEN_TABLE;
    $conn_w = $this->establishConnection('w');

    $tokens = PhabricatorTypeaheadDatasource::tokenizeString(
      $this->getUserName().' '.$this->getRealName());

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

  public function sendWelcomeEmail(PhabricatorUser $admin) {
    if (!$this->canEstablishWebSessions()) {
      throw new Exception(
        pht(
          'Can not send welcome mail to users who can not establish '.
          'web sessions!'));
    }

    $admin_username = $admin->getUserName();
    $admin_realname = $admin->getRealName();
    $user_username = $this->getUserName();
    $is_serious = PhabricatorEnv::getEnvConfig('phabricator.serious-business');

    $base_uri = PhabricatorEnv::getProductionURI('/');

    $engine = new PhabricatorAuthSessionEngine();
    $uri = $engine->getOneTimeLoginURI(
      $this,
      $this->loadPrimaryEmail(),
      PhabricatorAuthSessionEngine::ONETIME_WELCOME);

    $body = pht(
      "Welcome to Phabricator!\n\n".
      "%s (%s) has created an account for you.\n\n".
      "  Username: %s\n\n".
      "To login to Phabricator, follow this link and set a password:\n\n".
      "  %s\n\n".
      "After you have set a password, you can login in the future by ".
      "going here:\n\n".
      "  %s\n",
      $admin_username,
      $admin_realname,
      $user_username,
      $uri,
      $base_uri);

    if (!$is_serious) {
      $body .= sprintf(
        "\n%s\n",
        pht("Love,\nPhabricator"));
    }

    $mail = id(new PhabricatorMetaMTAMail())
      ->addTos(array($this->getPHID()))
      ->setForceDelivery(true)
      ->setSubject(pht('[Phabricator] Welcome to Phabricator'))
      ->setBody($body)
      ->saveAndSend();
  }

  public function sendUsernameChangeEmail(
    PhabricatorUser $admin,
    $old_username) {

    $admin_username = $admin->getUserName();
    $admin_realname = $admin->getRealName();
    $new_username = $this->getUserName();

    $password_instructions = null;
    if (PhabricatorPasswordAuthProvider::getPasswordProvider()) {
      $engine = new PhabricatorAuthSessionEngine();
      $uri = $engine->getOneTimeLoginURI(
        $this,
        null,
        PhabricatorAuthSessionEngine::ONETIME_USERNAME);
      $password_instructions = sprintf(
        "%s\n\n  %s\n\n%s\n",
        pht(
          "If you use a password to login, you'll need to reset it ".
          "before you can login again. You can reset your password by ".
          "following this link:"),
        $uri,
        pht(
          "And, of course, you'll need to use your new username to login ".
          "from now on. If you use OAuth to login, nothing should change."));
    }

    $body = sprintf(
      "%s\n\n  %s\n  %s\n\n%s",
      pht(
        '%s (%s) has changed your Phabricator username.',
        $admin_username,
        $admin_realname),
      pht(
        'Old Username: %s',
        $old_username),
      pht(
        'New Username: %s',
        $new_username),
      $password_instructions);

    $mail = id(new PhabricatorMetaMTAMail())
      ->addTos(array($this->getPHID()))
      ->setForceDelivery(true)
      ->setSubject(pht('[Phabricator] Username Changed'))
      ->setBody($body)
      ->saveAndSend();
  }

  public static function describeValidUsername() {
    return pht(
      'Usernames must contain only numbers, letters, period, underscore and '.
      'hyphen, and can not end with a period. They must have no more than %d '.
      'characters.',
      new PhutilNumber(self::MAXIMUM_USERNAME_LENGTH));
  }

  public static function validateUsername($username) {
    // NOTE: If you update this, make sure to update:
    //
    //  - Remarkup rule for @mentions.
    //  - Routing rule for "/p/username/".
    //  - Unit tests, obviously.
    //  - describeValidUsername() method, above.

    if (strlen($username) > self::MAXIMUM_USERNAME_LENGTH) {
      return false;
    }

    return (bool)preg_match('/^[a-zA-Z0-9._-]*[a-zA-Z0-9_-]\z/', $username);
  }

  public static function getDefaultProfileImageURI() {
    return celerity_get_resource_uri('/rsrc/image/avatar.png');
  }

  public function attachProfileImageURI($uri) {
    $this->profileImage = $uri;
    return $this;
  }

  public function getProfileImageURI() {
    return $this->assertAttached($this->profileImage);
  }

  public function getFullName() {
    if (strlen($this->getRealName())) {
      return $this->getUsername().' ('.$this->getRealName().')';
    } else {
      return $this->getUsername();
    }
  }

  public function getTimeZone() {
    return new DateTimeZone($this->getTimezoneIdentifier());
  }

  public function getPreference($key) {
    $preferences = $this->loadPreferences();

    // TODO: After T4103 and T7707 this should eventually be pushed down the
    // stack into modular preference definitions and role profiles. This is
    // just fixing T8601 and mildly anticipating those changes.
    $value = $preferences->getPreference($key);

    $allowed_values = null;
    switch ($key) {
      case PhabricatorUserPreferences::PREFERENCE_TIME_FORMAT:
        $allowed_values = array(
          'g:i A',
          'H:i',
        );
        break;
      case PhabricatorUserPreferences::PREFERENCE_DATE_FORMAT:
        $allowed_values = array(
          'Y-m-d',
          'n/j/Y',
          'd-m-Y',
        );
        break;
    }

    if ($allowed_values !== null) {
      $allowed_values = array_fuse($allowed_values);
      if (empty($allowed_values[$value])) {
        $value = head($allowed_values);
      }
    }

    return $value;
  }

  public function __toString() {
    return $this->getUsername();
  }

  public static function loadOneWithEmailAddress($address) {
    $email = id(new PhabricatorUserEmail())->loadOneWhere(
      'address = %s',
      $address);
    if (!$email) {
      return null;
    }
    return id(new PhabricatorUser())->loadOneWhere(
      'phid = %s',
      $email->getUserPHID());
  }

  public function getDefaultSpacePHID() {
    // TODO: We might let the user switch which space they're "in" later on;
    // for now just use the global space if one exists.

    // If the viewer has access to the default space, use that.
    $spaces = PhabricatorSpacesNamespaceQuery::getViewerActiveSpaces($this);
    foreach ($spaces as $space) {
      if ($space->getIsDefaultNamespace()) {
        return $space->getPHID();
      }
    }

    // Otherwise, use the space with the lowest ID that they have access to.
    // This just tends to keep the default stable and predictable over time,
    // so adding a new space won't change behavior for users.
    if ($spaces) {
      $spaces = msort($spaces, 'getID');
      return head($spaces)->getPHID();
    }

    return null;
  }


  /**
   * Grant a user a source of authority, to let them bypass policy checks they
   * could not otherwise.
   */
  public function grantAuthority($authority) {
    $this->authorities[] = $authority;
    return $this;
  }


  /**
   * Get authorities granted to the user.
   */
  public function getAuthorities() {
    return $this->authorities;
  }


/* -(  Availability  )------------------------------------------------------- */


  /**
   * @task availability
   */
  public function attachAvailability(array $availability) {
    $this->availability = $availability;
    return $this;
  }


  /**
   * Get the timestamp the user is away until, if they are currently away.
   *
   * @return int|null Epoch timestamp, or `null` if the user is not away.
   * @task availability
   */
  public function getAwayUntil() {
    $availability = $this->availability;

    $this->assertAttached($availability);
    if (!$availability) {
      return null;
    }

    return idx($availability, 'until');
  }


  /**
   * Describe the user's availability.
   *
   * @param PhabricatorUser Viewing user.
   * @return string Human-readable description of away status.
   * @task availability
   */
  public function getAvailabilityDescription(PhabricatorUser $viewer) {
    $until = $this->getAwayUntil();
    if ($until) {
      return pht('Away until %s', phabricator_datetime($until, $viewer));
    } else {
      return pht('Available');
    }
  }


  /**
   * Get cached availability, if present.
   *
   * @return wild|null Cache data, or null if no cache is available.
   * @task availability
   */
  public function getAvailabilityCache() {
    $now = PhabricatorTime::getNow();
    if ($this->availabilityCacheTTL <= $now) {
      return null;
    }

    try {
      return phutil_json_decode($this->availabilityCache);
    } catch (Exception $ex) {
      return null;
    }
  }


  /**
   * Write to the availability cache.
   *
   * @param wild Availability cache data.
   * @param int|null Cache TTL.
   * @return this
   * @task availability
   */
  public function writeAvailabilityCache(array $availability, $ttl) {
    $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
    queryfx(
      $this->establishConnection('w'),
      'UPDATE %T SET availabilityCache = %s, availabilityCacheTTL = %nd
        WHERE id = %d',
      $this->getTableName(),
      json_encode($availability),
      $ttl,
      $this->getID());
    unset($unguarded);

    return $this;
  }


/* -(  Profile Image Cache  )------------------------------------------------ */


  /**
   * Get this user's cached profile image URI.
   *
   * @return string|null Cached URI, if a URI is cached.
   * @task image-cache
   */
  public function getProfileImageCache() {
    $version = $this->getProfileImageVersion();

    $parts = explode(',', $this->profileImageCache, 2);
    if (count($parts) !== 2) {
      return null;
    }

    if ($parts[0] !== $version) {
      return null;
    }

    return $parts[1];
  }


  /**
   * Generate a new cache value for this user's profile image.
   *
   * @return string New cache value.
   * @task image-cache
   */
  public function writeProfileImageCache($uri) {
    $version = $this->getProfileImageVersion();
    $cache = "{$version},{$uri}";

    $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
    queryfx(
      $this->establishConnection('w'),
      'UPDATE %T SET profileImageCache = %s WHERE id = %d',
      $this->getTableName(),
      $cache,
      $this->getID());
    unset($unguarded);
  }


  /**
   * Get a version identifier for a user's profile image.
   *
   * This version will change if the image changes, or if any of the
   * environment configuration which goes into generating a URI changes.
   *
   * @return string Cache version.
   * @task image-cache
   */
  private function getProfileImageVersion() {
    $parts = array(
      PhabricatorEnv::getCDNURI('/'),
      PhabricatorEnv::getEnvConfig('cluster.instance'),
      $this->getProfileImagePHID(),
    );
    $parts = serialize($parts);
    return PhabricatorHash::digestForIndex($parts);
  }


/* -(  Multi-Factor Authentication  )---------------------------------------- */


  /**
   * Update the flag storing this user's enrollment in multi-factor auth.
   *
   * With certain settings, we need to check if a user has MFA on every page,
   * so we cache MFA enrollment on the user object for performance. Calling this
   * method synchronizes the cache by examining enrollment records. After
   * updating the cache, use @{method:getIsEnrolledInMultiFactor} to check if
   * the user is enrolled.
   *
   * This method should be called after any changes are made to a given user's
   * multi-factor configuration.
   *
   * @return void
   * @task factors
   */
  public function updateMultiFactorEnrollment() {
    $factors = id(new PhabricatorAuthFactorConfig())->loadAllWhere(
      'userPHID = %s',
      $this->getPHID());

    $enrolled = count($factors) ? 1 : 0;
    if ($enrolled !== $this->isEnrolledInMultiFactor) {
      $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
        queryfx(
          $this->establishConnection('w'),
          'UPDATE %T SET isEnrolledInMultiFactor = %d WHERE id = %d',
          $this->getTableName(),
          $enrolled,
          $this->getID());
      unset($unguarded);

      $this->isEnrolledInMultiFactor = $enrolled;
    }
  }


  /**
   * Check if the user is enrolled in multi-factor authentication.
   *
   * Enrolled users have one or more multi-factor authentication sources
   * attached to their account. For performance, this value is cached. You
   * can use @{method:updateMultiFactorEnrollment} to update the cache.
   *
   * @return bool True if the user is enrolled.
   * @task factors
   */
  public function getIsEnrolledInMultiFactor() {
    return $this->isEnrolledInMultiFactor;
  }


/* -(  Omnipotence  )-------------------------------------------------------- */


  /**
   * Returns true if this user is omnipotent. Omnipotent users bypass all policy
   * checks.
   *
   * @return bool True if the user bypasses policy checks.
   */
  public function isOmnipotent() {
    return $this->omnipotent;
  }


  /**
   * Get an omnipotent user object for use in contexts where there is no acting
   * user, notably daemons.
   *
   * @return PhabricatorUser An omnipotent user.
   */
  public static function getOmnipotentUser() {
    static $user = null;
    if (!$user) {
      $user = new PhabricatorUser();
      $user->omnipotent = true;
      $user->makeEphemeral();
    }
    return $user;
  }


  /**
   * Get a scalar string identifying this user.
   *
   * This is similar to using the PHID, but distinguishes between ominpotent
   * and public users explicitly. This allows safe construction of cache keys
   * or cache buckets which do not conflate public and omnipotent users.
   *
   * @return string Scalar identifier.
   */
  public function getCacheFragment() {
    if ($this->isOmnipotent()) {
      return 'u.omnipotent';
    }

    $phid = $this->getPHID();
    if ($phid) {
      return 'u.'.$phid;
    }

    return 'u.public';
  }


/* -(  Managing Handles  )--------------------------------------------------- */


  /**
   * Get a @{class:PhabricatorHandleList} which benefits from this viewer's
   * internal handle pool.
   *
   * @param list<phid> List of PHIDs to load.
   * @return PhabricatorHandleList Handle list object.
   * @task handle
   */
  public function loadHandles(array $phids) {
    if ($this->handlePool === null) {
      $this->handlePool = id(new PhabricatorHandlePool())
        ->setViewer($this);
    }

    return $this->handlePool->newHandleList($phids);
  }


  /**
   * Get a @{class:PHUIHandleView} for a single handle.
   *
   * This benefits from the viewer's internal handle pool.
   *
   * @param phid PHID to render a handle for.
   * @return PHUIHandleView View of the handle.
   * @task handle
   */
  public function renderHandle($phid) {
    return $this->loadHandles(array($phid))->renderHandle($phid);
  }


  /**
   * Get a @{class:PHUIHandleListView} for a list of handles.
   *
   * This benefits from the viewer's internal handle pool.
   *
   * @param list<phid> List of PHIDs to render.
   * @return PHUIHandleListView View of the handles.
   * @task handle
   */
  public function renderHandleList(array $phids) {
    return $this->loadHandles($phids)->renderList();
  }

  public function attachBadgePHIDs(array $phids) {
    $this->badgePHIDs = $phids;
    return $this;
  }

  public function getBadgePHIDs() {
    return $this->assertAttached($this->badgePHIDs);
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
      PhabricatorPolicyCapability::CAN_EDIT,
    );
  }

  public function getPolicy($capability) {
    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        return PhabricatorPolicies::POLICY_PUBLIC;
      case PhabricatorPolicyCapability::CAN_EDIT:
        if ($this->getIsSystemAgent() || $this->getIsMailingList()) {
          return PhabricatorPolicies::POLICY_ADMIN;
        } else {
          return PhabricatorPolicies::POLICY_NOONE;
        }
    }
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return $this->getPHID() && ($viewer->getPHID() === $this->getPHID());
  }

  public function describeAutomaticCapability($capability) {
    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_EDIT:
        return pht('Only you can edit your information.');
      default:
        return null;
    }
  }


/* -(  PhabricatorCustomFieldInterface  )------------------------------------ */


  public function getCustomFieldSpecificationForRole($role) {
    return PhabricatorEnv::getEnvConfig('user.fields');
  }

  public function getCustomFieldBaseClass() {
    return 'PhabricatorUserCustomField';
  }

  public function getCustomFields() {
    return $this->assertAttached($this->customFields);
  }

  public function attachCustomFields(PhabricatorCustomFieldAttachment $fields) {
    $this->customFields = $fields;
    return $this;
  }


/* -(  PhabricatorDestructibleInterface  )----------------------------------- */


  public function destroyObjectPermanently(
    PhabricatorDestructionEngine $engine) {

    $this->openTransaction();
      $this->delete();

      $externals = id(new PhabricatorExternalAccount())->loadAllWhere(
        'userPHID = %s',
        $this->getPHID());
      foreach ($externals as $external) {
        $external->delete();
      }

      $prefs = id(new PhabricatorUserPreferences())->loadAllWhere(
        'userPHID = %s',
        $this->getPHID());
      foreach ($prefs as $pref) {
        $pref->delete();
      }

      $profiles = id(new PhabricatorUserProfile())->loadAllWhere(
        'userPHID = %s',
        $this->getPHID());
      foreach ($profiles as $profile) {
        $profile->delete();
      }

      $keys = id(new PhabricatorAuthSSHKey())->loadAllWhere(
        'objectPHID = %s',
        $this->getPHID());
      foreach ($keys as $key) {
        $key->delete();
      }

      $emails = id(new PhabricatorUserEmail())->loadAllWhere(
        'userPHID = %s',
        $this->getPHID());
      foreach ($emails as $email) {
        $email->delete();
      }

      $sessions = id(new PhabricatorAuthSession())->loadAllWhere(
        'userPHID = %s',
        $this->getPHID());
      foreach ($sessions as $session) {
        $session->delete();
      }

      $factors = id(new PhabricatorAuthFactorConfig())->loadAllWhere(
        'userPHID = %s',
        $this->getPHID());
      foreach ($factors as $factor) {
        $factor->delete();
      }

    $this->saveTransaction();
  }


/* -(  PhabricatorSSHPublicKeyInterface  )----------------------------------- */


  public function getSSHPublicKeyManagementURI(PhabricatorUser $viewer) {
    if ($viewer->getPHID() == $this->getPHID()) {
      // If the viewer is managing their own keys, take them to the normal
      // panel.
      return '/settings/panel/ssh/';
    } else {
      // Otherwise, take them to the administrative panel for this user.
      return '/settings/'.$this->getID().'/panel/ssh/';
    }
  }

  public function getSSHKeyDefaultName() {
    return 'id_rsa_phabricator';
  }


/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */


  public function getApplicationTransactionEditor() {
    return new PhabricatorUserProfileEditor();
  }

  public function getApplicationTransactionObject() {
    return $this;
  }

  public function getApplicationTransactionTemplate() {
    return new PhabricatorUserTransaction();
  }

  public function willRenderTimeline(
    PhabricatorApplicationTransactionView $timeline,
    AphrontRequest $request) {
    return $timeline;
  }

}
