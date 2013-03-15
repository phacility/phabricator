<?php

final class ReleephRequestEvent extends ReleephDAO {

  const TYPE_CREATE         = 'create';
  const TYPE_STATUS         = 'status'; // old events
  const TYPE_USER_INTENT    = 'user-intent';
  const TYPE_PICK_STATUS    = 'pick-status';
  const TYPE_COMMIT         = 'commit';
  const TYPE_MANUAL_ACTION  = 'manual-action';
  const TYPE_DISCOVERY      = 'discovery';
  const TYPE_COMMENT        = 'comment';

  protected $releephRequestID;
  protected $type;
  protected $actorPHID;
  protected $details = array();

  public function getConfiguration() {
    return array(
      self::CONFIG_SERIALIZATION => array(
        'details' => self::SERIALIZATION_JSON,
      ),
    ) + parent::getConfiguration();
  }

  public function getDetail($key, $default = null) {
    return idx($this->details, $key, $default);
  }

  public function setDetail($key, $value) {
    $this->details[$key] = $value;
    return $this;
  }

  private function setDetails(array $details) {
    throw new Exception('Use setDetail()!');
  }

  public function setStatusBefore($status) {
    return $this->setDetail('oldStatus', $status);
  }

  public function setStatusAfter($status) {
    return $this->setDetail('newStatus', $status);
  }

  public function getStatusBefore() {
    return $this->getDetail('oldStatus');
  }

  public function getStatusAfter() {
    return $this->getDetail('newStatus');
  }

  public function getComment() {
    return $this->getDetail('comment');
  }

  public function extractPHIDs() {
    $phids = array();
    $phids[] = $this->actorPHID;
    foreach ($this->details as $key => $value) {
      if (strpos($key, 'PHID') !== false || strpos($key, 'phid') !== false) {
        $phids[] = $value;
      }
    }
    return $phids;
  }

  public function canGroupWith(ReleephRequestEvent $next) {
    if ($this->getActorPHID() != $next->getActorPHID()) {
      return false;
    }

    if ($this->getComment() && $next->getComment()) {
      return false;
    }

    // Break the chain if the next event changes the status
    if ($next->getStatusBefore() != $next->getStatusAfter()) {
      return false;
    }

    // Don't group if the next event starts off with a different status to the
    // one we ended with.  This probably shouldn't ever happen.
    if ($this->getStatusAfter() != $next->getStatusBefore()) {
      return false;
    }

    return true;
  }

}
