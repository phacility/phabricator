<?php

final class PhabricatorSystemActionEngine extends Phobject {

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

    $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
      self::recordAction($actors, $action, $score);
    unset($unguarded);
  }

  public static function loadBlockedActors(
    array $actors,
    PhabricatorSystemAction $action,
    $score) {

    $scores = self::loadScores($actors, $action);
    $window = self::getWindow();

    $blocked = array();
    foreach ($scores as $actor => $actor_score) {
      $actor_score = $actor_score + ($score / $window);
      if ($action->shouldBlockActor($actor, $actor_score)) {
        $blocked[$actor] = $actor_score;
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
      $actor_hashes[] = PhabricatorHash::digestForIndex($actor);
    }

    $log = new PhabricatorSystemActionLog();

    $window = self::getWindow();

    $conn_r = $log->establishConnection('r');
    $scores = queryfx_all(
      $conn_r,
      'SELECT actorIdentity, SUM(score) totalScore FROM %T
        WHERE action = %s AND actorHash IN (%Ls)
          AND epoch >= %d GROUP BY actorHash',
      $log->getTableName(),
      $action->getActionConstant(),
      $actor_hashes,
      (time() - $window));

    $scores = ipull($scores, 'totalScore', 'actorIdentity');

    foreach ($scores as $key => $score) {
      $scores[$key] = $score / $window;
    }

    $scores = $scores + array_fill_keys($actors, 0);

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
          VALUES %Q',
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

}
