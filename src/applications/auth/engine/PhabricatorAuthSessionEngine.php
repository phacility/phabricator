<?php

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

    // NOTE: We're being clever here because this happens on every page load,
    // and by joining we can save a query.

    $info = queryfx_one(
      $conn_r,
      'SELECT s.sessionExpires AS _sessionExpires, s.id AS _sessionID, u.*
        FROM %T u JOIN %T s ON u.phid = s.userPHID
        AND s.type = %s AND s.sessionKey = %s',
      $user_table->getTableName(),
      $session_table->getTableName(),
      $session_type,
      PhabricatorHash::digest($session_token));

    if (!$info) {
      return null;
    }

    $expires = $info['_sessionExpires'];
    $id = $info['_sessionID'];
    unset($info['_sessionExpires']);
    unset($info['_sessionID']);

    $ttl = PhabricatorAuthSession::getSessionTypeTTL($session_type);

    // If more than 20% of the time on this session has been used, refresh the
    // TTL back up to the full duration. The idea here is that sessions are
    // good forever if used regularly, but get GC'd when they fall out of use.

    if (time() + (0.80 * $ttl) > $expires) {
      $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
        $conn_w = $session_table->establishConnection('w');
        queryfx(
          $conn_w,
          'UPDATE %T SET sessionExpires = UNIX_TIMESTAMP() + %d WHERE id = %d',
          $session_table->getTableName(),
          $ttl,
          $id);
      unset($unguarded);
    }

    return $user_table->loadFromArray($info);
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
   * @return  string    Newly generated session key.
   */
  public function establishSession($session_type, $identity_phid) {
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

    // Logging-in users don't have CSRF stuff yet, so we have to unguard this
    // write.
    $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
      id(new PhabricatorAuthSession())
        ->setUserPHID($identity_phid)
        ->setType($session_type)
        ->setSessionKey(PhabricatorHash::digest($session_key))
        ->setSessionStart(time())
        ->setSessionExpires(time() + $session_ttl)
        ->save();

      $log = PhabricatorUserLog::initializeNewLog(
        null,
        $identity_phid,
        PhabricatorUserLog::ACTION_LOGIN);
      $log->setDetails(
        array(
          'session_type' => $session_type,
        ));
      $log->setSession($session_key);
      $log->save();
    unset($unguarded);

    return $session_key;
  }

}
