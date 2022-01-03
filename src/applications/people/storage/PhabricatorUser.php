<?php

/**
 * @task availability Availability
 * @task image-cache Profile Image Cache
 * @task factors Multi-Factor Authentication
 * @task handles Managing Handles
 * @task settings Settings
 * @task cache User Cache
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
    PhabricatorApplicationTransactionInterface,
    PhabricatorFulltextInterface,
    PhabricatorFerretInterface,
    PhabricatorConduitResultInterface,
    PhabricatorAuthPasswordHashInterface {

  const SESSION_TABLE = 'phabricator_session';
  const NAMETOKEN_TABLE = 'user_nametoken';
  const MAXIMUM_USERNAME_LENGTH = 64;

  protected $userName;
  protected $realName;
  protected $profileImagePHID;
  protected $defaultProfileImagePHID;
  protected $defaultProfileImageVersion;
  protected $availabilityCache;
  protected $availabilityCacheTTL;

  protected $conduitCertificate;

  protected $isSystemAgent = 0;
  protected $isMailingList = 0;
  protected $isAdmin = 0;
  protected $isDisabled = 0;
  protected $isEmailVerified = 0;
  protected $isApproved = 0;
  protected $isEnrolledInMultiFactor = 0;

  protected $accountSecret;

  private $profile = null;
  private $availability = self::ATTACHABLE;
  private $preferences = null;
  private $omnipotent = false;
  private $customFields = self::ATTACHABLE;
  private $badgePHIDs = self::ATTACHABLE;

  private $alternateCSRFString = self::ATTACHABLE;
  private $session = self::ATTACHABLE;
  private $rawCacheData = array();
  private $usableCacheData = array();

  private $handlePool;
  private $csrfSalt;

  private $settingCacheKeys = array();
  private $settingCache = array();
  private $allowInlineCacheGeneration;
  private $conduitClusterToken = self::ATTACHABLE;

  protected function readField($field) {
    switch ($field) {
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
    if (!$this->isLoggedIn()) {
      return false;
    }

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


  /**
   * Is this a user who we can reasonably expect to respond to requests?
   *
   * This is used to provide a grey "disabled/unresponsive" dot cue when
   * rendering handles and tags, so it isn't a surprise if you get ignored
   * when you ask things of users who will not receive notifications or could
   * not respond to them (because they are disabled, unapproved, do not have
   * verified email addresses, etc).
   *
   * @return bool True if this user can receive and respond to requests from
   *   other humans.
   */
  public function isResponsive() {
    if (!$this->isUserActivated()) {
      return false;
    }

    if (!$this->getIsEmailVerified()) {
      return false;
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
    if ($this->getIsDisabled()) {
      return false;
    }

    // Intracluster requests are permitted even if the user is logged out:
    // in particular, public users are allowed to issue intracluster requests
    // when browsing Diffusion.
    if (PhabricatorEnv::isClusterRemoteAddress()) {
      if (!$this->isLoggedIn()) {
        return true;
      }
    }

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
        'profileImagePHID' => 'phid?',
        'conduitCertificate' => 'text255',
        'isSystemAgent' => 'bool',
        'isMailingList' => 'bool',
        'isDisabled' => 'bool',
        'isAdmin' => 'bool',
        'isEmailVerified' => 'uint32',
        'isApproved' => 'uint32',
        'accountSecret' => 'bytes64',
        'isEnrolledInMultiFactor' => 'bool',
        'availabilityCache' => 'text255?',
        'availabilityCacheTTL' => 'uint32?',
        'defaultProfileImagePHID' => 'phid?',
        'defaultProfileImageVersion' => 'text64?',
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
        'availabilityCache' => true,
        'availabilityCacheTTL' => true,
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorPeopleUserPHIDType::TYPECONST);
  }

  public function getMonogram() {
    return '@'.$this->getUsername();
  }

  public function isLoggedIn() {
    return !($this->getPHID() === null);
  }

  public function saveWithoutIndex() {
    return parent::save();
  }

  public function save() {
    if (!$this->getConduitCertificate()) {
      $this->setConduitCertificate($this->generateConduitCertificate());
    }

    $secret = $this->getAccountSecret();
    if (($secret === null) || !strlen($secret)) {
      $this->setAccountSecret(Filesystem::readRandomCharacters(64));
    }

    $result = $this->saveWithoutIndex();

    if ($this->profile) {
      $this->profile->save();
    }

    $this->updateNameTokens();

    PhabricatorSearchWorker::queueDocumentForIndexing($this->getPHID());

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

  public function hasHighSecuritySession() {
    if (!$this->hasSession()) {
      return false;
    }

    return $this->getSession()->isHighSecuritySession();
  }

  private function generateConduitCertificate() {
    return Filesystem::readRandomCharacters(255);
  }

  const EMAIL_CYCLE_FREQUENCY = 86400;
  const EMAIL_TOKEN_LENGTH    = 24;

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
      $this->profile = PhabricatorUserProfile::initializeNewProfile($this);
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
    return id(new PhabricatorUserEmail())->loadOneWhere(
      'userPHID = %s AND isPrimary = 1',
      $this->getPHID());
  }


/* -(  Settings  )----------------------------------------------------------- */


  public function getUserSetting($key) {
    // NOTE: We store available keys and cached values separately to make it
    // faster to check for `null` in the cache, which is common.
    if (isset($this->settingCacheKeys[$key])) {
      return $this->settingCache[$key];
    }

    $settings_key = PhabricatorUserPreferencesCacheType::KEY_PREFERENCES;
    if ($this->getPHID()) {
      $settings = $this->requireCacheData($settings_key);
    } else {
      $settings = $this->loadGlobalSettings();
    }

    if (array_key_exists($key, $settings)) {
      $value = $settings[$key];
      return $this->writeUserSettingCache($key, $value);
    }

    $cache = PhabricatorCaches::getRuntimeCache();
    $cache_key = "settings.defaults({$key})";
    $cache_map = $cache->getKeys(array($cache_key));

    if ($cache_map) {
      $value = $cache_map[$cache_key];
    } else {
      $defaults = PhabricatorSetting::getAllSettings();
      if (isset($defaults[$key])) {
        $value = id(clone $defaults[$key])
          ->setViewer($this)
          ->getSettingDefaultValue();
      } else {
        $value = null;
      }

      $cache->setKey($cache_key, $value);
    }

    return $this->writeUserSettingCache($key, $value);
  }


  /**
   * Test if a given setting is set to a particular value.
   *
   * @param const Setting key.
   * @param wild Value to compare.
   * @return bool True if the setting has the specified value.
   * @task settings
   */
  public function compareUserSetting($key, $value) {
    $actual = $this->getUserSetting($key);
    return ($actual == $value);
  }

  private function writeUserSettingCache($key, $value) {
    $this->settingCacheKeys[$key] = true;
    $this->settingCache[$key] = $value;
    return $value;
  }

  public function getTranslation() {
    return $this->getUserSetting(PhabricatorTranslationSetting::SETTINGKEY);
  }

  public function getTimezoneIdentifier() {
    return $this->getUserSetting(PhabricatorTimezoneSetting::SETTINGKEY);
  }

  public static function getGlobalSettingsCacheKey() {
    return 'user.settings.globals.v1';
  }

  private function loadGlobalSettings() {
    $cache_key = self::getGlobalSettingsCacheKey();
    $cache = PhabricatorCaches::getMutableStructureCache();

    $settings = $cache->getKey($cache_key);
    if (!$settings) {
      $preferences = PhabricatorUserPreferences::loadGlobalPreferences($this);
      $settings = $preferences->getPreferences();
      $cache->setKey($cache_key, $settings);
    }

    return $settings;
  }


  /**
   * Override the user's timezone identifier.
   *
   * This is primarily useful for unit tests.
   *
   * @param string New timezone identifier.
   * @return this
   * @task settings
   */
  public function overrideTimezoneIdentifier($identifier) {
    $timezone_key = PhabricatorTimezoneSetting::SETTINGKEY;
    $this->settingCacheKeys[$timezone_key] = true;
    $this->settingCache[$timezone_key] = $identifier;
    return $this;
  }

  public function getGender() {
    return $this->getUserSetting(PhabricatorPronounSetting::SETTINGKEY);
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
        'INSERT INTO %T (userID, token) VALUES %LQ',
        $table,
        $sql);
    }
  }

  public static function describeValidUsername() {
    return pht(
      'Usernames must contain only numbers, letters, period, underscore, and '.
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

  public function getProfileImageURI() {
    $uri_key = PhabricatorUserProfileImageCacheType::KEY_URI;
    return $this->requireCacheData($uri_key);
  }

  public function getUnreadNotificationCount() {
    $notification_key = PhabricatorUserNotificationCountCacheType::KEY_COUNT;
    return $this->requireCacheData($notification_key);
  }

  public function getUnreadMessageCount() {
    $message_key = PhabricatorUserMessageCountCacheType::KEY_COUNT;
    return $this->requireCacheData($message_key);
  }

  public function getRecentBadgeAwards() {
    $badges_key = PhabricatorUserBadgesCacheType::KEY_BADGES;
    return $this->requireCacheData($badges_key);
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

  public function getTimeZoneOffset() {
    $timezone = $this->getTimeZone();
    $now = new DateTime('@'.PhabricatorTime::getNow());
    $offset = $timezone->getOffset($now);

    // Javascript offsets are in minutes and have the opposite sign.
    $offset = -(int)($offset / 60);

    return $offset;
  }

  public function getTimeZoneOffsetInHours() {
    $offset = $this->getTimeZoneOffset();
    $offset = (int)round($offset / 60);
    $offset = -$offset;

    return $offset;
  }

  public function formatShortDateTime($when, $now = null) {
    if ($now === null) {
      $now = PhabricatorTime::getNow();
    }

    try {
      $when = new DateTime('@'.$when);
      $now = new DateTime('@'.$now);
    } catch (Exception $ex) {
      return null;
    }

    $zone = $this->getTimeZone();

    $when->setTimeZone($zone);
    $now->setTimeZone($zone);

    if ($when->format('Y') !== $now->format('Y')) {
      // Different year, so show "Feb 31 2075".
      $format = 'M j Y';
    } else if ($when->format('Ymd') !== $now->format('Ymd')) {
      // Same year but different month and day, so show "Feb 31".
      $format = 'M j';
    } else {
      // Same year, month and day so show a time of day.
      $pref_time = PhabricatorTimeFormatSetting::SETTINGKEY;
      $format = $this->getUserSetting($pref_time);
    }

    return $when->format($format);
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


  public function hasConduitClusterToken() {
    return ($this->conduitClusterToken !== self::ATTACHABLE);
  }

  public function attachConduitClusterToken(PhabricatorConduitToken $token) {
    $this->conduitClusterToken = $token;
    return $this;
  }

  public function getConduitClusterToken() {
    return $this->assertAttached($this->conduitClusterToken);
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


  public function getDisplayAvailability() {
    $availability = $this->availability;

    $this->assertAttached($availability);
    if (!$availability) {
      return null;
    }

    $busy = PhabricatorCalendarEventInvitee::AVAILABILITY_BUSY;

    return idx($availability, 'availability', $busy);
  }


  public function getAvailabilityEventPHID() {
    $availability = $this->availability;

    $this->assertAttached($availability);
    if (!$availability) {
      return null;
    }

    return idx($availability, 'eventPHID');
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
    if (PhabricatorEnv::isReadOnly()) {
      return $this;
    }

    $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
    queryfx(
      $this->establishConnection('w'),
      'UPDATE %T SET availabilityCache = %s, availabilityCacheTTL = %nd
        WHERE id = %d',
      $this->getTableName(),
      phutil_json_encode($availability),
      $ttl,
      $this->getID());
    unset($unguarded);

    return $this;
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
    $factors = id(new PhabricatorAuthFactorConfigQuery())
      ->setViewer($this)
      ->withUserPHIDs(array($this->getPHID()))
      ->withFactorProviderStatuses(
        array(
          PhabricatorAuthFactorProviderStatus::STATUS_ACTIVE,
          PhabricatorAuthFactorProviderStatus::STATUS_DEPRECATED,
        ))
      ->execute();

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
   * This is similar to using the PHID, but distinguishes between omnipotent
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

/* -(  CSRF  )--------------------------------------------------------------- */


  public function getCSRFToken() {
    if ($this->isOmnipotent()) {
      // We may end up here when called from the daemons. The omnipotent user
      // has no meaningful CSRF token, so just return `null`.
      return null;
    }

    return $this->newCSRFEngine()
      ->newToken();
  }

  public function validateCSRFToken($token) {
    return $this->newCSRFengine()
      ->isValidToken($token);
  }

  public function getAlternateCSRFString() {
    return $this->assertAttached($this->alternateCSRFString);
  }

  public function attachAlternateCSRFString($string) {
    $this->alternateCSRFString = $string;
    return $this;
  }

  private function newCSRFEngine() {
    if ($this->getPHID()) {
      $vec = $this->getPHID().$this->getAccountSecret();
    } else {
      $vec = $this->getAlternateCSRFString();
    }

    if ($this->hasSession()) {
      $vec = $vec.$this->getSession()->getSessionKey();
    }

    $engine = new PhabricatorAuthCSRFEngine();

    if ($this->csrfSalt === null) {
      $this->csrfSalt = $engine->newSalt();
    }

    $engine
      ->setSalt($this->csrfSalt)
      ->setSecret(new PhutilOpaqueEnvelope($vec));

    return $engine;
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

    $viewer = $engine->getViewer();

    $this->openTransaction();
      $this->delete();

      $externals = id(new PhabricatorExternalAccountQuery())
        ->setViewer($viewer)
        ->withUserPHIDs(array($this->getPHID()))
        ->newIterator();
      foreach ($externals as $external) {
        $engine->destroyObject($external);
      }

      $prefs = id(new PhabricatorUserPreferencesQuery())
        ->setViewer($viewer)
        ->withUsers(array($this))
        ->execute();
      foreach ($prefs as $pref) {
        $engine->destroyObject($pref);
      }

      $profiles = id(new PhabricatorUserProfile())->loadAllWhere(
        'userPHID = %s',
        $this->getPHID());
      foreach ($profiles as $profile) {
        $profile->delete();
      }

      $keys = id(new PhabricatorAuthSSHKeyQuery())
        ->setViewer($viewer)
        ->withObjectPHIDs(array($this->getPHID()))
        ->execute();
      foreach ($keys as $key) {
        $engine->destroyObject($key);
      }

      $emails = id(new PhabricatorUserEmail())->loadAllWhere(
        'userPHID = %s',
        $this->getPHID());
      foreach ($emails as $email) {
        $engine->destroyObject($email);
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
      return '/settings/user/'.$this->getUsername().'/page/ssh/';
    }
  }

  public function getSSHKeyDefaultName() {
    return 'id_rsa_phabricator';
  }

  public function getSSHKeyNotifyPHIDs() {
    return array(
      $this->getPHID(),
    );
  }


/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */


  public function getApplicationTransactionEditor() {
    return new PhabricatorUserTransactionEditor();
  }

  public function getApplicationTransactionTemplate() {
    return new PhabricatorUserTransaction();
  }


/* -(  PhabricatorFulltextInterface  )--------------------------------------- */


  public function newFulltextEngine() {
    return new PhabricatorUserFulltextEngine();
  }


/* -(  PhabricatorFerretInterface  )----------------------------------------- */


  public function newFerretEngine() {
    return new PhabricatorUserFerretEngine();
  }


/* -(  PhabricatorConduitResultInterface  )---------------------------------- */


  public function getFieldSpecificationsForConduit() {
    return array(
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('username')
        ->setType('string')
        ->setDescription(pht("The user's username.")),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('realName')
        ->setType('string')
        ->setDescription(pht("The user's real name.")),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('roles')
        ->setType('list<string>')
        ->setDescription(pht('List of account roles.')),
    );
  }

  public function getFieldValuesForConduit() {
    $roles = array();

    if ($this->getIsDisabled()) {
      $roles[] = 'disabled';
    }

    if ($this->getIsSystemAgent()) {
      $roles[] = 'bot';
    }

    if ($this->getIsMailingList()) {
      $roles[] = 'list';
    }

    if ($this->getIsAdmin()) {
      $roles[] = 'admin';
    }

    if ($this->getIsEmailVerified()) {
      $roles[] = 'verified';
    }

    if ($this->getIsApproved()) {
      $roles[] = 'approved';
    }

    if ($this->isUserActivated()) {
      $roles[] = 'activated';
    }

    return array(
      'username' => $this->getUsername(),
      'realName' => $this->getRealName(),
      'roles' => $roles,
    );
  }

  public function getConduitSearchAttachments() {
    return array(
      id(new PhabricatorPeopleAvailabilitySearchEngineAttachment())
        ->setAttachmentKey('availability'),
    );
  }


/* -(  User Cache  )--------------------------------------------------------- */


  /**
   * @task cache
   */
  public function attachRawCacheData(array $data) {
    $this->rawCacheData = $data + $this->rawCacheData;
    return $this;
  }

  public function setAllowInlineCacheGeneration($allow_cache_generation) {
    $this->allowInlineCacheGeneration = $allow_cache_generation;
    return $this;
  }

  /**
   * @task cache
   */
  protected function requireCacheData($key) {
    if (isset($this->usableCacheData[$key])) {
      return $this->usableCacheData[$key];
    }

    $type = PhabricatorUserCacheType::requireCacheTypeForKey($key);

    if (isset($this->rawCacheData[$key])) {
      $raw_value = $this->rawCacheData[$key];

      $usable_value = $type->getValueFromStorage($raw_value);
      $this->usableCacheData[$key] = $usable_value;

      return $usable_value;
    }

    // By default, we throw if a cache isn't available. This is consistent
    // with the standard `needX()` + `attachX()` + `getX()` interaction.
    if (!$this->allowInlineCacheGeneration) {
      throw new PhabricatorDataNotAttachedException($this);
    }

    $user_phid = $this->getPHID();

    // Try to read the actual cache before we generate a new value. We can
    // end up here via Conduit, which does not use normal sessions and can
    // not pick up a free cache load during session identification.
    if ($user_phid) {
      $raw_data = PhabricatorUserCache::readCaches(
        $type,
        $key,
        array($user_phid));
      if (array_key_exists($user_phid, $raw_data)) {
        $raw_value = $raw_data[$user_phid];
        $usable_value = $type->getValueFromStorage($raw_value);
        $this->rawCacheData[$key] = $raw_value;
        $this->usableCacheData[$key] = $usable_value;
        return $usable_value;
      }
    }

    $usable_value = $type->getDefaultValue();

    if ($user_phid) {
      $map = $type->newValueForUsers($key, array($this));
      if (array_key_exists($user_phid, $map)) {
        $raw_value = $map[$user_phid];
        $usable_value = $type->getValueFromStorage($raw_value);

        $this->rawCacheData[$key] = $raw_value;
        PhabricatorUserCache::writeCache(
          $type,
          $key,
          $user_phid,
          $raw_value);
      }
    }

    $this->usableCacheData[$key] = $usable_value;

    return $usable_value;
  }


  /**
   * @task cache
   */
  public function clearCacheData($key) {
    unset($this->rawCacheData[$key]);
    unset($this->usableCacheData[$key]);
    return $this;
  }


  public function getCSSValue($variable_key) {
    $preference = PhabricatorAccessibilitySetting::SETTINGKEY;
    $key = $this->getUserSetting($preference);

    $postprocessor = CelerityPostprocessor::getPostprocessor($key);
    $variables = $postprocessor->getVariables();

    if (!isset($variables[$variable_key])) {
      throw new Exception(
        pht(
          'Unknown CSS variable "%s"!',
          $variable_key));
    }

    return $variables[$variable_key];
  }

/* -(  PhabricatorAuthPasswordHashInterface  )------------------------------- */


  public function newPasswordDigest(
    PhutilOpaqueEnvelope $envelope,
    PhabricatorAuthPassword $password) {

    // Before passwords are hashed, they are digested. The goal of digestion
    // is twofold: to reduce the length of very long passwords to something
    // reasonable; and to salt the password in case the best available hasher
    // does not include salt automatically.

    // Users may choose arbitrarily long passwords, and attackers may try to
    // attack the system by probing it with very long passwords. When large
    // inputs are passed to hashers -- which are intentionally slow -- it
    // can result in unacceptably long runtimes. The classic attack here is
    // to try to log in with a 64MB password and see if that locks up the
    // machine for the next century. By digesting passwords to a standard
    // length first, the length of the raw input does not impact the runtime
    // of the hashing algorithm.

    // Some hashers like bcrypt are self-salting, while other hashers are not.
    // Applying salt while digesting passwords ensures that hashes are salted
    // whether we ultimately select a self-salting hasher or not.

    // For legacy compatibility reasons, old VCS and Account password digest
    // algorithms are significantly more complicated than necessary to achieve
    // these goals. This is because they once used a different hashing and
    // salting process. When we upgraded to the modern modular hasher
    // infrastructure, we just bolted it onto the end of the existing pipelines
    // so that upgrading didn't break all users' credentials.

    // New implementations can (and, generally, should) safely select the
    // simple HMAC SHA256 digest at the bottom of the function, which does
    // everything that a digest callback should without any needless legacy
    // baggage on top.

    if ($password->getLegacyDigestFormat() == 'v1') {
      switch ($password->getPasswordType()) {
        case PhabricatorAuthPassword::PASSWORD_TYPE_VCS:
          // Old VCS passwords use an iterated HMAC SHA1 as a digest algorithm.
          // They originally used this as a hasher, but it became a digest
          // algorithm once hashing was upgraded to include bcrypt.
          $digest = $envelope->openEnvelope();
          $salt = $this->getPHID();
          for ($ii = 0; $ii < 1000; $ii++) {
            $digest = PhabricatorHash::weakDigest($digest, $salt);
          }
          return new PhutilOpaqueEnvelope($digest);
        case PhabricatorAuthPassword::PASSWORD_TYPE_ACCOUNT:
          // Account passwords previously used this weird mess of salt and did
          // not digest the input to a standard length.

          // Beyond this being a weird special case, there are two actual
          // problems with this, although neither are particularly severe:

          // First, because we do not normalize the length of passwords, this
          // algorithm may make us vulnerable to DOS attacks where an attacker
          // attempts to use a very long input to slow down hashers.

          // Second, because the username is part of the hash algorithm,
          // renaming a user breaks their password. This isn't a huge deal but
          // it's pretty silly. There's no security justification for this
          // behavior, I just didn't think about the implication when I wrote
          // it originally.

          $parts = array(
            $this->getUsername(),
            $envelope->openEnvelope(),
            $this->getPHID(),
            $password->getPasswordSalt(),
          );

          return new PhutilOpaqueEnvelope(implode('', $parts));
      }
    }

    // For passwords which do not have some crazy legacy reason to use some
    // other digest algorithm, HMAC SHA256 is an excellent choice. It satisfies
    // the digest requirements and is simple.

    $digest = PhabricatorHash::digestHMACSHA256(
      $envelope->openEnvelope(),
      $password->getPasswordSalt());

    return new PhutilOpaqueEnvelope($digest);
  }

  public function newPasswordBlocklist(
    PhabricatorUser $viewer,
    PhabricatorAuthPasswordEngine $engine) {

    $list = array();
    $list[] = $this->getUsername();
    $list[] = $this->getRealName();

    $emails = id(new PhabricatorUserEmail())->loadAllWhere(
      'userPHID = %s',
      $this->getPHID());
    foreach ($emails as $email) {
      $list[] = $email->getAddress();
    }

    return $list;
  }


}
