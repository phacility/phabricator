<?php

final class PhabricatorAuthSessionEngine extends Phobject {

  public function loadUserForSession($session_type, $session_key) {
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
      PhabricatorHash::digest($session_key));

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
   * @param   const   Session type constant (see
   *                  @{class:PhabricatorAuthSession}).
   * @param   phid    Identity to establish a session for, usually a user PHID.
   * @return  string  Newly generated session key.
   */
  public function establishSession($session_type, $identity_phid) {
    $session_table = new PhabricatorAuthSession();
    $conn_w = $session_table->establishConnection('w');

    // Consume entropy to generate a new session key, forestalling the eventual
    // heat death of the universe.
    $session_key = Filesystem::readRandomCharacters(40);

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
