<?php

final class PhrequentUserTime extends PhrequentDAO
  implements PhabricatorPolicyInterface {

  protected $userPHID;
  protected $objectPHID;
  protected $note;
  protected $dateStarted;
  protected $dateEnded;

  private $preemptingEvents = self::ATTACHABLE;

  protected function getConfiguration() {
    return array(
      self::CONFIG_COLUMN_SCHEMA => array(
        'objectPHID' => 'phid?',
        'note' => 'text?',
        'dateStarted' => 'epoch',
        'dateEnded' => 'epoch?',
      ),
    ) + parent::getConfiguration();
  }

  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
    );
  }

  public function getPolicy($capability) {
    $policy = PhabricatorPolicies::POLICY_NOONE;

    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        // Since it's impossible to perform any meaningful computations with
        // time if a user can't view some of it, visibility on tracked time is
        // unrestricted. If we eventually lock it down, it should be per-user.
        // (This doesn't mean that users can see tracked objects.)
        return PhabricatorPolicies::getMostOpenPolicy();
    }

    return $policy;
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return ($viewer->getPHID() == $this->getUserPHID());
  }


  public function describeAutomaticCapability($capability) {
    return null;
  }

  public function attachPreemptingEvents(array $events) {
    $this->preemptingEvents = $events;
    return $this;
  }

  public function getPreemptingEvents() {
    return $this->assertAttached($this->preemptingEvents);
  }

  public function isPreempted() {
    if ($this->getDateEnded() !== null) {
      return false;
    }
    foreach ($this->getPreemptingEvents() as $event) {
      if ($event->getDateEnded() === null &&
          $event->getObjectPHID() != $this->getObjectPHID()) {
        return true;
      }
    }
    return false;
  }

}
