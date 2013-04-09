<?php

final class PhrequentTrackController
  extends PhrequentController {

  private $verb;
  private $phid;

  public function willProcessRequest(array $data) {
    $this->phid = $data['phid'];
    $this->verb = $data['verb'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    if (!$this->isStartingTracking() &&
        !$this->isStoppingTracking()) {
      throw new Exception('Unrecognized verb: ' . $this->verb);
    }

    if ($this->isStartingTracking()) {
      $this->startTracking($user, $this->phid);
    } else if ($this->isStoppingTracking()) {
      $this->stopTracking($user, $this->phid);
    }

    return id(new AphrontRedirectResponse());
  }

  private function isStartingTracking() {
    return $this->verb === 'start';
  }

  private function isStoppingTracking() {
    return $this->verb === 'stop';
  }

  private function startTracking($user, $phid) {
    $usertime = new PhrequentUserTime();
    $usertime->setDateStarted(time());
    $usertime->setUserPHID($user->getPHID());
    $usertime->setObjectPHID($phid);
    $usertime->save();
  }

  private function stopTracking($user, $phid) {
    if (!PhrequentUserTimeQuery::isUserTrackingObject($user, $phid)) {
      // Don't do anything, it's not being tracked.
      return;
    }

    $usertime_dao = new PhrequentUserTime();
    $conn = $usertime_dao->establishConnection('r');

    queryfx(
      $conn,
      'UPDATE %T usertime '.
      'SET usertime.dateEnded = UNIX_TIMESTAMP() '.
      'WHERE usertime.userPHID = %s '.
      'AND usertime.objectPHID = %s '.
      'AND usertime.dateEnded IS NULL '.
      'ORDER BY usertime.dateStarted, usertime.id DESC '.
      'LIMIT 1',
      $usertime_dao->getTableName(),
      $user->getPHID(),
      $phid);
  }

}
