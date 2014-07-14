<?php

final class PhrequentTrackingEditor extends PhabricatorEditor {

  public function startTracking(PhabricatorUser $user, $phid, $timestamp) {
    $usertime = new PhrequentUserTime();
    $usertime->setDateStarted($timestamp);
    $usertime->setUserPHID($user->getPHID());
    $usertime->setObjectPHID($phid);
    $usertime->save();

    return $phid;
  }

  public function stopTracking(
    PhabricatorUser $user,
    $phid,
    $timestamp,
    $note) {

    if (!PhrequentUserTimeQuery::isUserTrackingObject($user, $phid)) {
      // Don't do anything, it's not being tracked.
      return null;
    }

    $usertime_dao = new PhrequentUserTime();
    $conn = $usertime_dao->establishConnection('r');

    queryfx(
      $conn,
      'UPDATE %T usertime '.
      'SET usertime.dateEnded = %d, '.
      'usertime.note = %s '.
      'WHERE usertime.userPHID = %s '.
      'AND usertime.objectPHID = %s '.
      'AND usertime.dateEnded IS NULL '.
      'ORDER BY usertime.dateStarted, usertime.id DESC '.
      'LIMIT 1',
      $usertime_dao->getTableName(),
      $timestamp,
      $note,
      $user->getPHID(),
      $phid);

    return $phid;
  }

  public function stopTrackingTop(PhabricatorUser $user, $timestamp, $note) {
    $times = id(new PhrequentUserTimeQuery())
      ->setViewer($user)
      ->withUserPHIDs(array($user->getPHID()))
      ->withEnded(PhrequentUserTimeQuery::ENDED_NO)
      ->setOrder(PhrequentUserTimeQuery::ORDER_STARTED_DESC)
      ->execute();

    if (count($times) === 0) {
      // Nothing to stop tracking.
      return null;
    }

    $current = head($times);

    return $this->stopTracking(
      $user,
      $current->getObjectPHID(),
      $timestamp,
      $note);
  }

}
