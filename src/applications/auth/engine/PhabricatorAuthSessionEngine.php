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


  private $workflowKey;
  private $request;

  public function setWorkflowKey($workflow_key) {
    $this->workflowKey = $workflow_key;
    return $this;
  }

  public function getWorkflowKey() {

    // TODO: A workflow key should become required in order to issue an MFA
    // challenge, but allow things to keep working for now until we can update
    // callsites.
    if ($this->workflowKey === null) {
      return 'legacy';
    }

    return $this->workflowKey;
  }

  public function getRequest() {
    return $this->request;
  }


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
    $conn = $session_table->establishConnection('r');

    // TODO: See T13225. We're moving sessions to a more modern digest
    // algorithm, but still accept older cookies for compatibility.
    $session_key = PhabricatorAuthSession::newSessionDigest(
      new PhutilOpaqueEnvelope($session_token));
    $weak_key = PhabricatorHash::weakDigest($session_token);

    $cache_parts = $this->getUserCacheQueryParts($conn);
    list($cache_selects, $cache_joins, $cache_map, $types_map) = $cache_parts;

    $info = queryfx_one(
      $conn,
      'SELECT
          s.id AS s_id,
          s.phid AS s_phid,
          s.sessionExpires AS s_sessionExpires,
          s.sessionStart AS s_sessionStart,
          s.highSecurityUntil AS s_highSecurityUntil,
          s.isPartial AS s_isPartial,
          s.signedLegalpadDocuments as s_signedLegalpadDocuments,
          IF(s.sessionKey = %P, 1, 0) as s_weak,
          u.*
          %Q
        FROM %R u JOIN %R s ON u.phid = s.userPHID
        AND s.type = %s AND s.sessionKey IN (%P, %P) %Q',
      new PhutilOpaqueEnvelope($weak_key),
      $cache_selects,
      $user_table,
      $session_table,
      $session_type,
      new PhutilOpaqueEnvelope($session_key),
      new PhutilOpaqueEnvelope($weak_key),
      $cache_joins);

    if (!$info) {
      return null;
    }

    // TODO: Remove this, see T13225.
    $is_weak = (bool)$info['s_weak'];
    unset($info['s_weak']);

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

    $this->extendSession($session);

    // TODO: Remove this, see T13225.
    if ($is_weak) {
      $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
        $conn_w = $session_table->establishConnection('w');
        queryfx(
          $conn_w,
          'UPDATE %T SET sessionKey = %P WHERE id = %d',
          $session->getTableName(),
          new PhutilOpaqueEnvelope($session_key),
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
    $session_ttl = PhabricatorAuthSession::getSessionTypeTTL(
      $session_type,
      $partial);

    $digest_key = PhabricatorAuthSession::newSessionDigest(
      new PhutilOpaqueEnvelope($session_key));

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
          ? PhabricatorPartialLoginUserLogType::LOGTYPE
          : PhabricatorLoginUserLogType::LOGTYPE));

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
    PhutilOpaqueEnvelope $except_session = null) {

    $sessions = id(new PhabricatorAuthSessionQuery())
      ->setViewer($user)
      ->withIdentityPHIDs(array($user->getPHID()))
      ->execute();

    if ($except_session !== null) {
      $except_session = PhabricatorAuthSession::newSessionDigest(
        $except_session);
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
      PhabricatorLogoutUserLogType::LOGTYPE);
    $log->save();

    $extensions = PhabricatorAuthSessionEngineExtension::getAllExtensions();
    foreach ($extensions as $extension) {
      $extension->didLogout($user, array($session));
    }

    $session->delete();
  }


/* -(  High Security  )------------------------------------------------------ */


  /**
   * Require the user respond to a high security (MFA) check.
   *
   * This method differs from @{method:requireHighSecuritySession} in that it
   * does not upgrade the user's session as a side effect. This method is
   * appropriate for one-time checks.
   *
   * @param PhabricatorUser User whose session needs to be in high security.
   * @param AphrontRequest  Current request.
   * @param string          URI to return the user to if they cancel.
   * @return PhabricatorAuthHighSecurityToken Security token.
   * @task hisec
   */
  public function requireHighSecurityToken(
    PhabricatorUser $viewer,
    AphrontRequest $request,
    $cancel_uri) {

    return $this->newHighSecurityToken(
      $viewer,
      $request,
      $cancel_uri,
      false,
      false);
  }


  /**
   * Require high security, or prompt the user to enter high security.
   *
   * If the user's session is in high security, this method will return a
   * token. Otherwise, it will throw an exception which will eventually
   * be converted into a multi-factor authentication workflow.
   *
   * This method upgrades the user's session to high security for a short
   * period of time, and is appropriate if you anticipate they may need to
   * take multiple high security actions. To perform a one-time check instead,
   * use @{method:requireHighSecurityToken}.
   *
   * @param PhabricatorUser User whose session needs to be in high security.
   * @param AphrontRequest  Current request.
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

    return $this->newHighSecurityToken(
      $viewer,
      $request,
      $cancel_uri,
      $jump_into_hisec,
      true);
  }

  private function newHighSecurityToken(
    PhabricatorUser $viewer,
    AphrontRequest $request,
    $cancel_uri,
    $jump_into_hisec,
    $upgrade_session) {

    if (!$viewer->hasSession()) {
      throw new Exception(
        pht('Requiring a high-security session from a user with no session!'));
    }

    // TODO: If a user answers a "requireHighSecurityToken()" prompt and hits
    // a "requireHighSecuritySession()" prompt a short time later, the one-shot
    // token should be good enough to upgrade the session.

    $session = $viewer->getSession();

    // Check if the session is already in high security mode.
    $token = $this->issueHighSecurityToken($session);
    if ($token) {
      return $token;
    }

    // Load the multi-factor auth sources attached to this account. Note that
    // we order factors from oldest to newest, which is not the default query
    // ordering but makes the greatest sense in context.
    $factors = id(new PhabricatorAuthFactorConfigQuery())
      ->setViewer($viewer)
      ->withUserPHIDs(array($viewer->getPHID()))
      ->withFactorProviderStatuses(
        array(
          PhabricatorAuthFactorProviderStatus::STATUS_ACTIVE,
          PhabricatorAuthFactorProviderStatus::STATUS_DEPRECATED,
        ))
      ->execute();

    // Sort factors in the same order that they appear in on the Settings
    // panel. This means that administrators changing provider statuses may
    // change the order of prompts for users, but the alternative is that the
    // Settings panel order disagrees with the prompt order, which seems more
    // disruptive.
    $factors = msortv($factors, 'newSortVector');

    // If the account has no associated multi-factor auth, just issue a token
    // without putting the session into high security mode. This is generally
    // easier for users. A minor but desirable side effect is that when a user
    // adds an auth factor, existing sessions won't get a free pass into hisec,
    // since they never actually got marked as hisec.
    if (!$factors) {
      return $this->issueHighSecurityToken($session, true)
        ->setIsUnchallengedToken(true);
    }

    $this->request = $request;
    foreach ($factors as $factor) {
      $factor->setSessionEngine($this);
    }

    // Check for a rate limit without awarding points, so the user doesn't
    // get partway through the workflow only to get blocked.
    PhabricatorSystemActionEngine::willTakeAction(
      array($viewer->getPHID()),
      new PhabricatorAuthTryFactorAction(),
      0);

    $now = PhabricatorTime::getNow();

    // We need to do challenge validation first, since this happens whether you
    // submitted responses or not. You can't get a "bad response" error before
    // you actually submit a response, but you can get a "wait, we can't
    // issue a challenge yet" response. Load all issued challenges which are
    // currently valid.
    $challenges = id(new PhabricatorAuthChallengeQuery())
      ->setViewer($viewer)
      ->withFactorPHIDs(mpull($factors, 'getPHID'))
      ->withUserPHIDs(array($viewer->getPHID()))
      ->withChallengeTTLBetween($now, null)
      ->execute();

    PhabricatorAuthChallenge::newChallengeResponsesFromRequest(
      $challenges,
      $request);

    $challenge_map = mgroup($challenges, 'getFactorPHID');

    $validation_results = array();
    $ok = true;

    // Validate each factor against issued challenges. For example, this
    // prevents you from receiving or responding to a TOTP challenge if another
    // challenge was recently issued to a different session.
    foreach ($factors as $factor) {
      $factor_phid = $factor->getPHID();
      $issued_challenges = idx($challenge_map, $factor_phid, array());
      $provider = $factor->getFactorProvider();
      $impl = $provider->getFactor();

      $new_challenges = $impl->getNewIssuedChallenges(
        $factor,
        $viewer,
        $issued_challenges);

      // NOTE: We may get a list of challenges back, or may just get an early
      // result. For example, this can happen on an SMS factor if all SMS
      // mailers have been disabled.
      if ($new_challenges instanceof PhabricatorAuthFactorResult) {
        $result = $new_challenges;

        if (!$result->getIsValid()) {
          $ok = false;
        }

        $validation_results[$factor_phid] = $result;
        $challenge_map[$factor_phid] = $issued_challenges;
        continue;
      }

      foreach ($new_challenges as $new_challenge) {
        $issued_challenges[] = $new_challenge;
      }
      $challenge_map[$factor_phid] = $issued_challenges;

      if (!$issued_challenges) {
        continue;
      }

      $result = $impl->getResultFromIssuedChallenges(
        $factor,
        $viewer,
        $issued_challenges);

      if (!$result) {
        continue;
      }

      if (!$result->getIsValid()) {
        $ok = false;
      }

      $validation_results[$factor_phid] = $result;
    }

    if ($request->isHTTPPost()) {
      $request->validateCSRF();
      if ($request->getExists(AphrontRequest::TYPE_HISEC)) {

        // Limit factor verification rates to prevent brute force attacks.
        $any_attempt = false;
        foreach ($factors as $factor) {
          $factor_phid = $factor->getPHID();

          $provider = $factor->getFactorProvider();
          $impl = $provider->getFactor();

          // If we already have a result (normally "wait..."), we won't try
          // to validate whatever the user submitted, so this doesn't count as
          // an attempt for rate limiting purposes.
          if (isset($validation_results[$factor_phid])) {
            continue;
          }

          if ($impl->getRequestHasChallengeResponse($factor, $request)) {
            $any_attempt = true;
            break;
          }
        }

        if ($any_attempt) {
          PhabricatorSystemActionEngine::willTakeAction(
            array($viewer->getPHID()),
            new PhabricatorAuthTryFactorAction(),
            1);
        }

        foreach ($factors as $factor) {
          $factor_phid = $factor->getPHID();

          // If we already have a validation result from previously issued
          // challenges, skip validating this factor.
          if (isset($validation_results[$factor_phid])) {
            continue;
          }

          $issued_challenges = idx($challenge_map, $factor_phid, array());

          $provider = $factor->getFactorProvider();
          $impl = $provider->getFactor();

          $validation_result = $impl->getResultFromChallengeResponse(
            $factor,
            $viewer,
            $request,
            $issued_challenges);

          if (!$validation_result->getIsValid()) {
            $ok = false;
          }

          $validation_results[$factor_phid] = $validation_result;
        }

        if ($ok) {
          // We're letting you through, so mark all the challenges you
          // responded to as completed. These challenges can never be used
          // again, even by the same session and workflow: you can't use the
          // same response to take two different actions, even if those actions
          // are of the same type.
          foreach ($validation_results as $validation_result) {
            $challenge = $validation_result->getAnsweredChallenge()
              ->markChallengeAsCompleted();
          }

          // Give the user a credit back for a successful factor verification.
          if ($any_attempt) {
            PhabricatorSystemActionEngine::willTakeAction(
              array($viewer->getPHID()),
              new PhabricatorAuthTryFactorAction(),
              -1);
          }

          if ($session->getIsPartial() && !$jump_into_hisec) {
            // If we have a partial session and are not jumping directly into
            // hisec, just issue a token without putting it in high security
            // mode.
            return $this->issueHighSecurityToken($session, true);
          }

          // If we aren't upgrading the session itself, just issue a token.
          if (!$upgrade_session) {
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
            PhabricatorEnterHisecUserLogType::LOGTYPE);
          $log->save();
        } else {
          $log = PhabricatorUserLog::initializeNewLog(
            $viewer,
            $viewer->getPHID(),
            PhabricatorFailHisecUserLogType::LOGTYPE);
          $log->save();
        }
      }
    }

    $token = $this->issueHighSecurityToken($session);
    if ($token) {
      return $token;
    }

    // If we don't have a validation result for some factors yet, fill them
    // in with an empty result so form rendering doesn't have to care if the
    // results exist or not. This happens when you first load the form and have
    // not submitted any responses yet.
    foreach ($factors as $factor) {
      $factor_phid = $factor->getPHID();
      if (isset($validation_results[$factor_phid])) {
        continue;
      }

      $issued_challenges = idx($challenge_map, $factor_phid, array());

      $validation_results[$factor_phid] = $impl->getResultForPrompt(
        $factor,
        $viewer,
        $request,
        $issued_challenges);
    }

    throw id(new PhabricatorAuthHighSecurityRequiredException())
      ->setCancelURI($cancel_uri)
      ->setIsSessionUpgrade($upgrade_session)
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

    if ($session->isHighSecuritySession() || $force) {
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
    assert_instances_of($validation_results, 'PhabricatorAuthFactorResult');

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendRemarkupInstructions('');

    $answered = array();
    foreach ($factors as $factor) {
      $result = $validation_results[$factor->getPHID()];

      $provider = $factor->getFactorProvider();
      $impl = $provider->getFactor();

      $impl->renderValidateFactorForm(
        $factor,
        $form,
        $viewer,
        $result);

      $answered_challenge = $result->getAnsweredChallenge();
      if ($answered_challenge) {
        $answered[] = $answered_challenge;
      }
    }

    $form->appendRemarkupInstructions('');

    if ($answered) {
      $http_params = PhabricatorAuthChallenge::newHTTPParametersFromChallenges(
        $answered);
      foreach ($http_params as $key => $value) {
        $form->addHiddenInput($key, $value);
      }
    }

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
      PhabricatorExitHisecUserLogType::LOGTYPE);
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
        PhabricatorFullLoginUserLogType::LOGTYPE);
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
          PhabricatorSignDocumentsUserLogType::LOGTYPE);
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
   * @param bool True to generate a URI which forces an immediate upgrade to
   *  a full session, bypassing MFA and other login checks.
   * @return string Login URI.
   * @task onetime
   */
  public function getOneTimeLoginURI(
    PhabricatorUser $user,
    PhabricatorUserEmail $email = null,
    $type = self::ONETIME_RESET,
    $force_full_session = false) {

    $key = Filesystem::readRandomCharacters(32);
    $key_hash = $this->getOneTimeLoginKeyHash($user, $email, $key);
    $onetime_type = PhabricatorAuthOneTimeLoginTemporaryTokenType::TOKENTYPE;

    $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
      $token = id(new PhabricatorAuthTemporaryToken())
        ->setTokenResource($user->getPHID())
        ->setTokenType($onetime_type)
        ->setTokenExpires(time() + phutil_units('1 day in seconds'))
        ->setTokenCode($key_hash)
        ->setShouldForceFullSession($force_full_session)
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

    return PhabricatorHash::weakDigest(implode(':', $parts));
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
      $cache_selects = qsprintf($conn, ', %LQ', $cache_selects);
    } else {
      $cache_selects = qsprintf($conn, '');
    }

    if ($cache_joins) {
      $cache_joins = qsprintf($conn, '%LJ', $cache_joins);
    } else {
      $cache_joins = qsprintf($conn, '');
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

  private function extendSession(PhabricatorAuthSession $session) {
    $is_partial = $session->getIsPartial();

    // Don't extend partial sessions. You have a relatively short window to
    // upgrade into a full session, and your session expires otherwise.
    if ($is_partial) {
      return;
    }

    $session_type = $session->getType();

    $ttl = PhabricatorAuthSession::getSessionTypeTTL(
      $session_type,
      $session->getIsPartial());

    // If more than 20% of the time on this session has been used, refresh the
    // TTL back up to the full duration. The idea here is that sessions are
    // good forever if used regularly, but get GC'd when they fall out of use.

    $now = PhabricatorTime::getNow();
    if ($now + (0.80 * $ttl) <= $session->getSessionExpires()) {
      return;
    }

    $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
      queryfx(
        $session->establishConnection('w'),
        'UPDATE %R SET sessionExpires = UNIX_TIMESTAMP() + %d
          WHERE id = %d',
        $session,
        $ttl,
        $session->getID());
    unset($unguarded);
  }


}
