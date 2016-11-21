<?php

/**
 *
 * @task use      Using Sessions
 * @task new      Creating Sessions
 * @task hisec    High Security
 * @task partial  Partial Sessions
 * @task onetime  One Time Login URIs
 * @task cache    User Cache
 */
final class PhabricatorAuthSessionEngine extends Phobject {

  /**
   * Session issued to normal users after they login through a standard channel.
   * Associates the client with a standard user identity.
   */
  const KIND_USER      = 'U';


  /**
   * Session issued to users who login with some sort of credentials but do not
   * have full accounts. These are sometimes called "grey users".
   *
   * TODO: We do not currently issue these sessions, see T4310.
   */
  const KIND_EXTERNAL  = 'X';


  /**
   * Session issued to logged-out users which has no real identity information.
   * Its purpose is to protect logged-out users from CSRF.
   */
  const KIND_ANONYMOUS = 'A';


  /**
   * Session kind isn't known.
   */
  const KIND_UNKNOWN   = '?';


  const ONETIME_RECOVER = 'recover';
  const ONETIME_RESET = 'reset';
  const ONETIME_WELCOME = 'welcome';
  const ONETIME_USERNAME = 'rename';


  /**
   * Get the session kind (e.g., anonymous, user, external account) from a
   * session token. Returns a `KIND_` constant.
   *
   * @param   string  Session token.
   * @return  const   Session kind constant.
   */
  public static function getSessionKindFromToken($session_token) {
    if (strpos($session_token, '/') === false) {
      // Old-style session, these are all user sessions.
      return self::KIND_USER;
    }

    list($kind, $key) = explode('/', $session_token, 2);

    switch ($kind) {
      case self::KIND_ANONYMOUS:
      case self::KIND_USER:
      case self::KIND_EXTERNAL:
        return $kind;
      default:
        return self::KIND_UNKNOWN;
    }
  }


  /**
   * Load the user identity associated with a session of a given type,
   * identified by token.
   *
   * When the user presents a session token to an API, this method verifies
   * it is of the correct type and loads the corresponding identity if the
   * session exists and is valid.
   *
   * NOTE: `$session_type` is the type of session that is required by the
   * loading context. This prevents use of a Conduit sesssion as a Web
   * session, for example.
   *
   * @param const The type of session to load.
   * @param string The session token.
   * @return PhabricatorUser|null
   * @task use
   */
  public function loadUserForSession($session_type, $session_token) {
    $session_kind = self::getSessionKindFromToken($session_token);
    switch ($session_kind) {
      case self::KIND_ANONYMOUS:
        // Don't bother trying to load a user for an anonymous session, since
        // neither the session nor the user exist.
        return null;
      case self::KIND_UNKNOWN:
        // If we don't know what kind of session this is, don't go looking for
        // it.
        return null;
      case self::KIND_USER:
        break;
      case self::KIND_EXTERNAL:
        // TODO: Implement these (T4310).
        return null;
    }

    $session_table = new PhabricatorAuthSession();
    $user_table = new PhabricatorUser();
    $conn_r = $session_table->establishConnection('r');
    $session_key = PhabricatorHash::digest($session_token);

    $cache_parts = $this->getUserCacheQueryParts($conn_r);
    list($cache_selects, $cache_joins, $cache_map, $types_map) = $cache_parts;

    $info = queryfx_one(
      $conn_r,
      'SELECT
          s.id AS s_id,
          s.sessionExpires AS s_sessionExpires,
          s.sessionStart AS s_sessionStart,
          s.highSecurityUntil AS s_highSecurityUntil,
          s.isPartial AS s_isPartial,
          s.signedLegalpadDocuments as s_signedLegalpadDocuments,
          u.*
          %Q
        FROM %T u JOIN %T s ON u.phid = s.userPHID
        AND s.type = %s AND s.sessionKey = %s %Q',
      $cache_selects,
      $user_table->getTableName(),
      $session_table->getTableName(),
      $session_type,
      $session_key,
      $cache_joins);

    if (!$info) {
      return null;
    }

    $session_dict = array(
      'userPHID' => $info['phid'],
      'sessionKey' => $session_key,
      'type' => $session_type,
    );

    $cache_raw = array_fill_keys($cache_map, null);
    foreach ($info as $key => $value) {
      if (strncmp($key, 's_', 2) === 0) {
        unset($info[$key]);
        $session_dict[substr($key, 2)] = $value;
        continue;
      }

      if (isset($cache_map[$key])) {
        unset($info[$key]);
        $cache_raw[$cache_map[$key]] = $value;
        continue;
      }
    }

    $user = $user_table->loadFromArray($info);

    $cache_raw = $this->filterRawCacheData($user, $types_map, $cache_raw);
    $user->attachRawCacheData($cache_raw);

    switch ($session_type) {
      case PhabricatorAuthSession::TYPE_WEB:
        // Explicitly prevent bots and mailing lists from establishing web
        // sessions. It's normally impossible to attach authentication to these
        // accounts, and likewise impossible to generate sessions, but it's
        // technically possible that a session could exist in the database. If
        // one does somehow, refuse to load it.
        if (!$user->canEstablishWebSessions()) {
          return null;
        }
        break;
    }

    $session = id(new PhabricatorAuthSession())->loadFromArray($session_dict);

    $ttl = PhabricatorAuthSession::getSessionTypeTTL($session_type);

    // If more than 20% of the time on this session has been used, refresh the
    // TTL back up to the full duration. The idea here is that sessions are
    // good forever if used regularly, but get GC'd when they fall out of use.

    // NOTE: If we begin rotating session keys when extending sessions, the
    // CSRF code needs to be updated so CSRF tokens survive session rotation.

    if (time() + (0.80 * $ttl) > $session->getSessionExpires()) {
      $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
        $conn_w = $session_table->establishConnection('w');
        queryfx(
          $conn_w,
          'UPDATE %T SET sessionExpires = UNIX_TIMESTAMP() + %d WHERE id = %d',
          $session->getTableName(),
          $ttl,
          $session->getID());
      unset($unguarded);
    }

    $user->attachSession($session);
    return $user;
  }


  /**
   * Issue a new session key for a given identity. Phabricator supports
   * different types of sessions (like "web" and "conduit") and each session
   * type may have multiple concurrent sessions (this allows a user to be
   * logged in on multiple browsers at the same time, for instance).
   *
   * Note that this method is transport-agnostic and does not set cookies or
   * issue other types of tokens, it ONLY generates a new session key.
   *
   * You can configure the maximum number of concurrent sessions for various
   * session types in the Phabricator configuration.
   *
   * @param   const     Session type constant (see
   *                    @{class:PhabricatorAuthSession}).
   * @param   phid|null Identity to establish a session for, usually a user
   *                    PHID. With `null`, generates an anonymous session.
   * @param   bool      True to issue a partial session.
   * @return  string    Newly generated session key.
   */
  public function establishSession($session_type, $identity_phid, $partial) {
    // Consume entropy to generate a new session key, forestalling the eventual
    // heat death of the universe.
    $session_key = Filesystem::readRandomCharacters(40);

    if ($identity_phid === null) {
      return self::KIND_ANONYMOUS.'/'.$session_key;
    }

    $session_table = new PhabricatorAuthSession();
    $conn_w = $session_table->establishConnection('w');

    // This has a side effect of validating the session type.
    $session_ttl = PhabricatorAuthSession::getSessionTypeTTL($session_type);

    $digest_key = PhabricatorHash::digest($session_key);

    // Logging-in users don't have CSRF stuff yet, so we have to unguard this
    // write.
    $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
      id(new PhabricatorAuthSession())
        ->setUserPHID($identity_phid)
        ->setType($session_type)
        ->setSessionKey($digest_key)
        ->setSessionStart(time())
        ->setSessionExpires(time() + $session_ttl)
        ->setIsPartial($partial ? 1 : 0)
        ->setSignedLegalpadDocuments(0)
        ->save();

      $log = PhabricatorUserLog::initializeNewLog(
        null,
        $identity_phid,
        ($partial
          ? PhabricatorUserLog::ACTION_LOGIN_PARTIAL
          : PhabricatorUserLog::ACTION_LOGIN));

      $log->setDetails(
        array(
          'session_type' => $session_type,
        ));
      $log->setSession($digest_key);
      $log->save();
    unset($unguarded);

    $info = id(new PhabricatorAuthSessionInfo())
      ->setSessionType($session_type)
      ->setIdentityPHID($identity_phid)
      ->setIsPartial($partial);

    $extensions = PhabricatorAuthSessionEngineExtension::getAllExtensions();
    foreach ($extensions as $extension) {
      $extension->didEstablishSession($info);
    }

    return $session_key;
  }


  /**
   * Terminate all of a user's login sessions.
   *
   * This is used when users change passwords, linked accounts, or add
   * multifactor authentication.
   *
   * @param PhabricatorUser User whose sessions should be terminated.
   * @param string|null Optionally, one session to keep. Normally, the current
   *   login session.
   *
   * @return void
   */
  public function terminateLoginSessions(
    PhabricatorUser $user,
    $except_session = null) {

    $sessions = id(new PhabricatorAuthSessionQuery())
      ->setViewer($user)
      ->withIdentityPHIDs(array($user->getPHID()))
      ->execute();

    if ($except_session !== null) {
      $except_session = PhabricatorHash::digest($except_session);
    }

    foreach ($sessions as $key => $session) {
      if ($except_session !== null) {
        $is_except = phutil_hashes_are_identical(
          $session->getSessionKey(),
          $except_session);
        if ($is_except) {
          continue;
        }
      }

      $session->delete();
    }
  }

  public function logoutSession(
    PhabricatorUser $user,
    PhabricatorAuthSession $session) {

    $log = PhabricatorUserLog::initializeNewLog(
      $user,
      $user->getPHID(),
      PhabricatorUserLog::ACTION_LOGOUT);
    $log->save();

    $extensions = PhabricatorAuthSessionEngineExtension::getAllExtensions();
    foreach ($extensions as $extension) {
      $extension->didLogout($user, array($session));
    }

    $session->delete();
  }


/* -(  High Security  )------------------------------------------------------ */


  /**
   * Require high security, or prompt the user to enter high security.
   *
   * If the user's session is in high security, this method will return a
   * token. Otherwise, it will throw an exception which will eventually
   * be converted into a multi-factor authentication workflow.
   *
   * @param PhabricatorUser User whose session needs to be in high security.
   * @param AphrontReqeust  Current request.
   * @param string          URI to return the user to if they cancel.
   * @param bool            True to jump partial sessions directly into high
   *                        security instead of just upgrading them to full
   *                        sessions.
   * @return PhabricatorAuthHighSecurityToken Security token.
   * @task hisec
   */
  public function requireHighSecuritySession(
    PhabricatorUser $viewer,
    AphrontRequest $request,
    $cancel_uri,
    $jump_into_hisec = false) {

    if (!$viewer->hasSession()) {
      throw new Exception(
        pht('Requiring a high-security session from a user with no session!'));
    }

    $session = $viewer->getSession();

    // Check if the session is already in high security mode.
    $token = $this->issueHighSecurityToken($session);
    if ($token) {
      return $token;
    }

    // Load the multi-factor auth sources attached to this account.
    $factors = id(new PhabricatorAuthFactorConfig())->loadAllWhere(
      'userPHID = %s',
      $viewer->getPHID());

    // If the account has no associated multi-factor auth, just issue a token
    // without putting the session into high security mode. This is generally
    // easier for users. A minor but desirable side effect is that when a user
    // adds an auth factor, existing sessions won't get a free pass into hisec,
    // since they never actually got marked as hisec.
    if (!$factors) {
      return $this->issueHighSecurityToken($session, true);
    }

    // Check for a rate limit without awarding points, so the user doesn't
    // get partway through the workflow only to get blocked.
    PhabricatorSystemActionEngine::willTakeAction(
      array($viewer->getPHID()),
      new PhabricatorAuthTryFactorAction(),
      0);

    $validation_results = array();
    if ($request->isHTTPPost()) {
      $request->validateCSRF();
      if ($request->getExists(AphrontRequest::TYPE_HISEC)) {

        // Limit factor verification rates to prevent brute force attacks.
        PhabricatorSystemActionEngine::willTakeAction(
          array($viewer->getPHID()),
          new PhabricatorAuthTryFactorAction(),
          1);

        $ok = true;
        foreach ($factors as $factor) {
          $id = $factor->getID();
          $impl = $factor->requireImplementation();

          $validation_results[$id] = $impl->processValidateFactorForm(
            $factor,
            $viewer,
            $request);

          if (!$impl->isFactorValid($factor, $validation_results[$id])) {
            $ok = false;
          }
        }

        if ($ok) {
          // Give the user a credit back for a successful factor verification.
          PhabricatorSystemActionEngine::willTakeAction(
            array($viewer->getPHID()),
            new PhabricatorAuthTryFactorAction(),
            -1);

          if ($session->getIsPartial() && !$jump_into_hisec) {
            // If we have a partial session and are not jumping directly into
            // hisec, just issue a token without putting it in high security
            // mode.
            return $this->issueHighSecurityToken($session, true);
          }

          $until = time() + phutil_units('15 minutes in seconds');
          $session->setHighSecurityUntil($until);

          queryfx(
            $session->establishConnection('w'),
            'UPDATE %T SET highSecurityUntil = %d WHERE id = %d',
            $session->getTableName(),
            $until,
            $session->getID());

          $log = PhabricatorUserLog::initializeNewLog(
            $viewer,
            $viewer->getPHID(),
            PhabricatorUserLog::ACTION_ENTER_HISEC);
          $log->save();
        } else {
          $log = PhabricatorUserLog::initializeNewLog(
            $viewer,
            $viewer->getPHID(),
            PhabricatorUserLog::ACTION_FAIL_HISEC);
          $log->save();
        }
      }
    }

    $token = $this->issueHighSecurityToken($session);
    if ($token) {
      return $token;
    }

    throw id(new PhabricatorAuthHighSecurityRequiredException())
      ->setCancelURI($cancel_uri)
      ->setFactors($factors)
      ->setFactorValidationResults($validation_results);
  }


  /**
   * Issue a high security token for a session, if authorized.
   *
   * @param PhabricatorAuthSession Session to issue a token for.
   * @param bool Force token issue.
   * @return PhabricatorAuthHighSecurityToken|null Token, if authorized.
   * @task hisec
   */
  private function issueHighSecurityToken(
    PhabricatorAuthSession $session,
    $force = false) {

    $until = $session->getHighSecurityUntil();
    if ($until > time() || $force) {
      return new PhabricatorAuthHighSecurityToken();
    }

    return null;
  }


  /**
   * Render a form for providing relevant multi-factor credentials.
   *
   * @param PhabricatorUser Viewing user.
   * @param AphrontRequest Current request.
   * @return AphrontFormView Renderable form.
   * @task hisec
   */
  public function renderHighSecurityForm(
    array $factors,
    array $validation_results,
    PhabricatorUser $viewer,
    AphrontRequest $request) {

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendRemarkupInstructions('');

    foreach ($factors as $factor) {
      $factor->requireImplementation()->renderValidateFactorForm(
        $factor,
        $form,
        $viewer,
        idx($validation_results, $factor->getID()));
    }

    $form->appendRemarkupInstructions('');

    return $form;
  }


  /**
   * Strip the high security flag from a session.
   *
   * Kicks a session out of high security and logs the exit.
   *
   * @param PhabricatorUser Acting user.
   * @param PhabricatorAuthSession Session to return to normal security.
   * @return void
   * @task hisec
   */
  public function exitHighSecurity(
    PhabricatorUser $viewer,
    PhabricatorAuthSession $session) {

    if (!$session->getHighSecurityUntil()) {
      return;
    }

    queryfx(
      $session->establishConnection('w'),
      'UPDATE %T SET highSecurityUntil = NULL WHERE id = %d',
      $session->getTableName(),
      $session->getID());

    $log = PhabricatorUserLog::initializeNewLog(
      $viewer,
      $viewer->getPHID(),
      PhabricatorUserLog::ACTION_EXIT_HISEC);
    $log->save();
  }


/* -(  Partial Sessions  )--------------------------------------------------- */


  /**
   * Upgrade a partial session to a full session.
   *
   * @param PhabricatorAuthSession Session to upgrade.
   * @return void
   * @task partial
   */
  public function upgradePartialSession(PhabricatorUser $viewer) {

    if (!$viewer->hasSession()) {
      throw new Exception(
        pht('Upgrading partial session of user with no session!'));
    }

    $session = $viewer->getSession();

    if (!$session->getIsPartial()) {
      throw new Exception(pht('Session is not partial!'));
    }

    $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
      $session->setIsPartial(0);

      queryfx(
        $session->establishConnection('w'),
        'UPDATE %T SET isPartial = %d WHERE id = %d',
        $session->getTableName(),
        0,
        $session->getID());

      $log = PhabricatorUserLog::initializeNewLog(
        $viewer,
        $viewer->getPHID(),
        PhabricatorUserLog::ACTION_LOGIN_FULL);
      $log->save();
    unset($unguarded);
  }


/* -(  Legalpad Documents )-------------------------------------------------- */


  /**
   * Upgrade a session to have all legalpad documents signed.
   *
   * @param PhabricatorUser User whose session should upgrade.
   * @param array LegalpadDocument objects
   * @return void
   * @task partial
   */
  public function signLegalpadDocuments(PhabricatorUser $viewer, array $docs) {

    if (!$viewer->hasSession()) {
      throw new Exception(
        pht('Signing session legalpad documents of user with no session!'));
    }

    $session = $viewer->getSession();

    if ($session->getSignedLegalpadDocuments()) {
      throw new Exception(pht(
        'Session has already signed required legalpad documents!'));
    }

    $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
      $session->setSignedLegalpadDocuments(1);

      queryfx(
        $session->establishConnection('w'),
        'UPDATE %T SET signedLegalpadDocuments = %d WHERE id = %d',
        $session->getTableName(),
        1,
        $session->getID());

      if (!empty($docs)) {
        $log = PhabricatorUserLog::initializeNewLog(
          $viewer,
          $viewer->getPHID(),
          PhabricatorUserLog::ACTION_LOGIN_LEGALPAD);
        $log->save();
      }
    unset($unguarded);
  }


/* -(  One Time Login URIs  )------------------------------------------------ */


  /**
   * Retrieve a temporary, one-time URI which can log in to an account.
   *
   * These URIs are used for password recovery and to regain access to accounts
   * which users have been locked out of.
   *
   * @param PhabricatorUser User to generate a URI for.
   * @param PhabricatorUserEmail Optionally, email to verify when
   *  link is used.
   * @param string Optional context string for the URI. This is purely cosmetic
   *  and used only to customize workflow and error messages.
   * @return string Login URI.
   * @task onetime
   */
  public function getOneTimeLoginURI(
    PhabricatorUser $user,
    PhabricatorUserEmail $email = null,
    $type = self::ONETIME_RESET) {

    $key = Filesystem::readRandomCharacters(32);
    $key_hash = $this->getOneTimeLoginKeyHash($user, $email, $key);
    $onetime_type = PhabricatorAuthOneTimeLoginTemporaryTokenType::TOKENTYPE;

    $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
      id(new PhabricatorAuthTemporaryToken())
        ->setTokenResource($user->getPHID())
        ->setTokenType($onetime_type)
        ->setTokenExpires(time() + phutil_units('1 day in seconds'))
        ->setTokenCode($key_hash)
        ->save();
    unset($unguarded);

    $uri = '/login/once/'.$type.'/'.$user->getID().'/'.$key.'/';
    if ($email) {
      $uri = $uri.$email->getID().'/';
    }

    try {
      $uri = PhabricatorEnv::getProductionURI($uri);
    } catch (Exception $ex) {
      // If a user runs `bin/auth recover` before configuring the base URI,
      // just show the path. We don't have any way to figure out the domain.
      // See T4132.
    }

    return $uri;
  }


  /**
   * Load the temporary token associated with a given one-time login key.
   *
   * @param PhabricatorUser User to load the token for.
   * @param PhabricatorUserEmail Optionally, email to verify when
   *  link is used.
   * @param string Key user is presenting as a valid one-time login key.
   * @return PhabricatorAuthTemporaryToken|null Token, if one exists.
   * @task onetime
   */
  public function loadOneTimeLoginKey(
    PhabricatorUser $user,
    PhabricatorUserEmail $email = null,
    $key = null) {

    $key_hash = $this->getOneTimeLoginKeyHash($user, $email, $key);
    $onetime_type = PhabricatorAuthOneTimeLoginTemporaryTokenType::TOKENTYPE;

    return id(new PhabricatorAuthTemporaryTokenQuery())
      ->setViewer($user)
      ->withTokenResources(array($user->getPHID()))
      ->withTokenTypes(array($onetime_type))
      ->withTokenCodes(array($key_hash))
      ->withExpired(false)
      ->executeOne();
  }


  /**
   * Hash a one-time login key for storage as a temporary token.
   *
   * @param PhabricatorUser User this key is for.
   * @param PhabricatorUserEmail Optionally, email to verify when
   *  link is used.
   * @param string The one time login key.
   * @return string Hash of the key.
   * task onetime
   */
  private function getOneTimeLoginKeyHash(
    PhabricatorUser $user,
    PhabricatorUserEmail $email = null,
    $key = null) {

    $parts = array(
      $key,
      $user->getAccountSecret(),
    );

    if ($email) {
      $parts[] = $email->getVerificationCode();
    }

    return PhabricatorHash::digest(implode(':', $parts));
  }


/* -(  User Cache  )--------------------------------------------------------- */


  /**
   * @task cache
   */
  private function getUserCacheQueryParts(AphrontDatabaseConnection $conn) {
    $cache_selects = array();
    $cache_joins = array();
    $cache_map = array();

    $keys = array();
    $types_map = array();

    $cache_types = PhabricatorUserCacheType::getAllCacheTypes();
    foreach ($cache_types as $cache_type) {
      foreach ($cache_type->getAutoloadKeys() as $autoload_key) {
        $keys[] = $autoload_key;
        $types_map[$autoload_key] = $cache_type;
      }
    }

    $cache_table = id(new PhabricatorUserCache())->getTableName();

    $cache_idx = 1;
    foreach ($keys as $key) {
      $join_as = 'ucache_'.$cache_idx;
      $select_as = 'ucache_'.$cache_idx.'_v';

      $cache_selects[] = qsprintf(
        $conn,
        '%T.cacheData %T',
        $join_as,
        $select_as);

      $cache_joins[] = qsprintf(
        $conn,
        'LEFT JOIN %T AS %T ON u.phid = %T.userPHID
          AND %T.cacheIndex = %s',
        $cache_table,
        $join_as,
        $join_as,
        $join_as,
        PhabricatorHash::digestForIndex($key));

      $cache_map[$select_as] = $key;

      $cache_idx++;
    }

    if ($cache_selects) {
      $cache_selects = ', '.implode(', ', $cache_selects);
    } else {
      $cache_selects = '';
    }

    if ($cache_joins) {
      $cache_joins = implode(' ', $cache_joins);
    } else {
      $cache_joins = '';
    }

    return array($cache_selects, $cache_joins, $cache_map, $types_map);
  }

  private function filterRawCacheData(
    PhabricatorUser $user,
    array $types_map,
    array $cache_raw) {

    foreach ($cache_raw as $cache_key => $cache_data) {
      $type = $types_map[$cache_key];
      if ($type->shouldValidateRawCacheData()) {
        if (!$type->isRawCacheDataValid($user, $cache_key, $cache_data)) {
          unset($cache_raw[$cache_key]);
        }
      }
    }

    return $cache_raw;
  }

  public function willServeRequestForUser(PhabricatorUser $user) {
    // We allow the login user to generate any missing cache data inline.
    $user->setAllowInlineCacheGeneration(true);

    // Switch to the user's translation.
    PhabricatorEnv::setLocaleCode($user->getTranslation());

    $extensions = PhabricatorAuthSessionEngineExtension::getAllExtensions();
    foreach ($extensions as $extension) {
      $extension->willServeRequestForUser($user);
    }
  }

}
