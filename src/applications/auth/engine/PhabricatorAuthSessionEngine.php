<?php

final class PhabricatorAuthSessionEngine extends Phobject {

  public function loadUserForSession($session_type, $session_key) {
    $session_table = new PhabricatorAuthSession();
    $user_table = new PhabricatorUser();
    $conn_r = $session_table->establishConnection('w');

    $info = queryfx_one(
      $conn_r,
      'SELECT u.* FROM %T u JOIN %T s ON u.phid = s.userPHID
        AND s.type LIKE %> AND s.sessionKey = %s',
      $user_table->getTableName(),
      $session_table->getTableName(),
      $session_type.'-',
      PhabricatorHash::digest($session_key));

    if (!$info) {
      return null;
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
      case PhabricatorAuthSession::TYPE_WEB:
        $session_limit = PhabricatorEnv::getEnvConfig('auth.sessions.web');
        break;
      case PhabricatorAuthSession::TYPE_CONDUIT:
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

    // NOTE: Session establishment is sensitive to race conditions, as when
    // piping `arc` to `arc`:
    //
    //   arc export ... | arc paste ...
    //
    // To avoid this, we overwrite an old session only if it hasn't been
    // re-established since we read it.

    // Consume entropy to generate a new session key, forestalling the eventual
    // heat death of the universe.
    $session_key = Filesystem::readRandomCharacters(40);

    // Load all the currently active sessions.
    $sessions = queryfx_all(
      $conn_w,
      'SELECT type, sessionKey, sessionStart FROM %T
        WHERE userPHID = %s AND type LIKE %>',
      $session_table->getTableName(),
      $identity_phid,
      $session_type.'-');
    $sessions = ipull($sessions, null, 'type');
    $sessions = isort($sessions, 'sessionStart');

    $existing_sessions = array_keys($sessions);

    // UNGUARDED WRITES: Logging-in users don't have CSRF stuff yet.
    $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();

    $retries = 0;
    while (true) {

      // Choose which 'type' we'll actually establish, i.e. what number we're
      // going to append to the basic session type. To do this, just check all
      // the numbers sequentially until we find an available session.
      $establish_type = null;
      for ($ii = 1; $ii <= $session_limit; $ii++) {
        $try_type = $session_type.'-'.$ii;
        if (!in_array($try_type, $existing_sessions)) {
          $establish_type = $try_type;
          $expect_key = PhabricatorHash::digest($session_key);
          $existing_sessions[] = $try_type;

          // Ensure the row exists so we can issue an update below. We don't
          // care if we race here or not.
          queryfx(
            $conn_w,
            'INSERT IGNORE INTO %T (userPHID, type, sessionKey, sessionStart)
              VALUES (%s, %s, %s, 0)',
            $session_table->getTableName(),
            $identity_phid,
            $establish_type,
            PhabricatorHash::digest($session_key));
          break;
        }
      }

      // If we didn't find an available session, choose the oldest session and
      // overwrite it.
      if (!$establish_type) {
        $oldest = reset($sessions);
        $establish_type = $oldest['type'];
        $expect_key = $oldest['sessionKey'];
      }

      // This is so that we'll only overwrite the session if it hasn't been
      // refreshed since we read it. If it has, the session key will be
      // different and we know we're racing other processes. Whichever one
      // won gets the session, we go back and try again.

      queryfx(
        $conn_w,
        'UPDATE %T SET sessionKey = %s, sessionStart = UNIX_TIMESTAMP()
          WHERE userPHID = %s AND type = %s AND sessionKey = %s',
        $session_table->getTableName(),
        PhabricatorHash::digest($session_key),
        $identity_phid,
        $establish_type,
        $expect_key);

      if ($conn_w->getAffectedRows()) {
        // The update worked, so the session is valid.
        break;
      } else {
        // We know this just got grabbed, so don't try it again.
        unset($sessions[$establish_type]);
      }

      if (++$retries > $session_limit) {
        throw new Exception("Failed to establish a session!");
      }
    }

    $log = PhabricatorUserLog::initializeNewLog(
      null,
      $identity_phid,
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

}
