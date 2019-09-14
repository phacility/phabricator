<?php

final class PhabricatorSystemActionEngine extends Phobject {

  /**
   * Prepare to take an action, throwing an exception if the user has exceeded
   * the rate limit.
   *
   * The `$actors` are a list of strings. Normally this will be a list of
   * user PHIDs, but some systems use other identifiers (like email
   * addresses). Each actor's score threshold is tracked independently. If
   * any actor exceeds the rate limit for the action, this method throws.
   *
   * The `$action` defines the actual thing being rate limited, and sets the
   * limit.
   *
   * You can pass either a positive, zero, or negative `$score` to this method:
   *
   *   - If the score is positive, the user is given that many points toward
   *     the rate limit after the limit is checked. Over time, this will cause
   *     them to hit the rate limit and be prevented from taking further
   *     actions.
   *   - If the score is zero, the rate limit is checked but no score changes
   *     are made. This allows you to check for a rate limit before beginning
   *     a workflow, so the user doesn't fill in a form only to get rate limited
   *     at the end.
   *   - If the score is negative, the user is credited points, allowing them
   *     to take more actions than the limit normally permits. By awarding
   *     points for failed actions and credits for successful actions, a
   *     system can be sensitive to failure without overly restricting
   *     legitimate uses.
   *
   * If any actor is exceeding their rate limit, this method throws a
   * @{class:PhabricatorSystemActionRateLimitException}.
   *
   * @param list<string> List of actors.
   * @param PhabricatorSystemAction Action being taken.
   * @param float Score or credit, see above.
   * @return void
   */
  public static function willTakeAction(
    array $actors,
    PhabricatorSystemAction $action,
    $score) {

    // If the score for this action is negative, we're giving the user a credit,
    // so don't bother checking if they're blocked or not.
    if ($score >= 0) {
      $blocked = self::loadBlockedActors($actors, $action, $score);
      if ($blocked) {
        foreach ($blocked as $actor => $actor_score) {
          throw new PhabricatorSystemActionRateLimitException(
            $action,
            $actor_score);
        }
      }
    }

    if ($score != 0) {
      $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
        self::recordAction($actors, $action, $score);
      unset($unguarded);
    }
  }

  public static function loadBlockedActors(
    array $actors,
    PhabricatorSystemAction $action,
    $score) {

    $scores = self::loadScores($actors, $action);
    $window = self::getWindow();

    $blocked = array();
    foreach ($scores as $actor => $actor_score) {
      // For the purposes of checking for a block, we just use the raw
      // persistent score and do not include the score for this action. This
      // allows callers to test for a block without adding any points and get
      // the same result they would if they were adding points: we only
      // trigger a rate limit when the persistent score exceeds the threshold.
      if ($action->shouldBlockActor($actor, $actor_score)) {
        // When reporting the results, we do include the points for this
        // action. This makes the error messages more clear, since they
        // more accurately report the number of actions the user has really
        // tried to take.
        $blocked[$actor] = $actor_score + ($score / $window);
      }
    }

    return $blocked;
  }

  public static function loadScores(
    array $actors,
    PhabricatorSystemAction $action) {

    if (!$actors) {
      return array();
    }

    $actor_hashes = array();
    foreach ($actors as $actor) {
      $digest = PhabricatorHash::digestForIndex($actor);
      $actor_hashes[$digest] = $actor;
    }

    $log = new PhabricatorSystemActionLog();

    $window = self::getWindow();

    $conn = $log->establishConnection('r');

    $rows = queryfx_all(
      $conn,
      'SELECT actorHash, SUM(score) totalScore FROM %T
        WHERE action = %s AND actorHash IN (%Ls)
          AND epoch >= %d GROUP BY actorHash',
      $log->getTableName(),
      $action->getActionConstant(),
      array_keys($actor_hashes),
      (PhabricatorTime::getNow() - $window));

    $rows = ipull($rows, 'totalScore', 'actorHash');

    $scores = array();
    foreach ($actor_hashes as $digest => $actor) {
      $score = idx($rows, $digest, 0);
      $scores[$actor] = ($score / $window);
    }

    return $scores;
  }

  private static function recordAction(
    array $actors,
    PhabricatorSystemAction $action,
    $score) {

    $log = new PhabricatorSystemActionLog();
    $conn_w = $log->establishConnection('w');

    $sql = array();
    foreach ($actors as $actor) {
      $sql[] = qsprintf(
        $conn_w,
        '(%s, %s, %s, %f, %d)',
        PhabricatorHash::digestForIndex($actor),
        $actor,
        $action->getActionConstant(),
        $score,
        time());
    }

    foreach (PhabricatorLiskDAO::chunkSQL($sql) as $chunk) {
      queryfx(
        $conn_w,
        'INSERT INTO %T (actorHash, actorIdentity, action, score, epoch)
          VALUES %LQ',
        $log->getTableName(),
        $chunk);
    }
  }

  private static function getWindow() {
    // Limit queries to the last hour of data so we don't need to look at as
    // many rows. We can use an arbitrarily larger window instead (we normalize
    // scores to actions per second) but all the actions we care about limiting
    // have a limit much higher than one action per hour.
    return phutil_units('1 hour in seconds');
  }


  /**
   * Reset all action counts for actions taken by some set of actors in the
   * previous action window.
   *
   * @param list<string> Actors to reset counts for.
   * @return int Number of actions cleared.
   */
  public static function resetActions(array $actors) {
    $log = new PhabricatorSystemActionLog();
    $conn_w = $log->establishConnection('w');

    $now = PhabricatorTime::getNow();

    $hashes = array();
    foreach ($actors as $actor) {
      $hashes[] = PhabricatorHash::digestForIndex($actor);
    }

    queryfx(
      $conn_w,
      'DELETE FROM %T
        WHERE actorHash IN (%Ls) AND epoch BETWEEN %d AND %d',
      $log->getTableName(),
      $hashes,
      $now - self::getWindow(),
      $now);

    return $conn_w->getAffectedRows();
  }

  public static function newActorFromRequest(AphrontRequest $request) {
    return $request->getRemoteAddress();
  }

}
