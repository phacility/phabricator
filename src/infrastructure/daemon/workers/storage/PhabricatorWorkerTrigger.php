<?php

final class PhabricatorWorkerTrigger
  extends PhabricatorWorkerDAO
  implements
    PhabricatorDestructibleInterface,
    PhabricatorPolicyInterface {

  protected $triggerVersion;
  protected $clockClass;
  protected $clockProperties;
  protected $actionClass;
  protected $actionProperties;

  private $action = self::ATTACHABLE;
  private $clock = self::ATTACHABLE;
  private $event = self::ATTACHABLE;

  protected function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'clockProperties' => self::SERIALIZATION_JSON,
        'actionProperties' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'triggerVersion' => 'uint32',
        'clockClass' => 'text64',
        'actionClass' => 'text64',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_trigger' => array(
          'columns' => array('triggerVersion'),
          'unique' => true,
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function save() {
    $conn_w = $this->establishConnection('w');

    $this->openTransaction();
      $next_version = LiskDAO::loadNextCounterValue(
        $conn_w,
        PhabricatorTriggerDaemon::COUNTER_VERSION);
      $this->setTriggerVersion($next_version);

      $result = parent::save();
    $this->saveTransaction();

    return $this;
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorWorkerTriggerPHIDType::TYPECONST);
  }

  /**
   * Return the next time this trigger should execute.
   *
   * This method can be called either after the daemon executed the trigger
   * successfully (giving the trigger an opportunity to reschedule itself
   * into the future, if it is a recurring event) or after the trigger itself
   * is changed (usually because of an application edit). The `$is_reschedule`
   * parameter distinguishes between these cases.
   *
   * @param int|null Epoch of the most recent successful event execution.
   * @param bool `true` if we're trying to reschedule the event after
   *   execution; `false` if this is in response to a trigger update.
   * @return int|null Return an epoch to schedule the next event execution,
   *   or `null` to stop the event from executing again.
   */
  public function getNextEventEpoch($last_epoch, $is_reschedule) {
    return $this->getClock()->getNextEventEpoch($last_epoch, $is_reschedule);
  }


  /**
   * Execute the event.
   *
   * @param int|null Epoch of previous execution, or null if this is the first
   *   execution.
   * @param int Scheduled epoch of this execution. This may not be the same
   *   as the current time.
   * @return void
   */
  public function executeTrigger($last_event, $this_event) {
    return $this->getAction()->execute($last_event, $this_event);
  }

  public function getEvent() {
    return $this->assertAttached($this->event);
  }

  public function attachEvent(PhabricatorWorkerTriggerEvent $event = null) {
    $this->event = $event;
    return $this;
  }

  public function setAction(PhabricatorTriggerAction $action) {
    $this->actionClass = get_class($action);
    $this->actionProperties = $action->getProperties();
    return $this->attachAction($action);
  }

  public function getAction() {
    return $this->assertAttached($this->action);
  }

  public function attachAction(PhabricatorTriggerAction $action) {
    $this->action = $action;
    return $this;
  }

  public function setClock(PhabricatorTriggerClock $clock) {
    $this->clockClass = get_class($clock);
    $this->clockProperties = $clock->getProperties();
    return $this->attachClock($clock);
  }

  public function getClock() {
    return $this->assertAttached($this->clock);
  }

  public function attachClock(PhabricatorTriggerClock $clock) {
    $this->clock = $clock;
    return $this;
  }


  /**
   * Predict the epoch at which this trigger will next fire.
   *
   * @return int|null  Epoch when the event will next fire, or `null` if it is
   *   not planned to trigger.
   */
  public function getNextEventPrediction() {
    // NOTE: We're basically echoing the database state here, so this won't
    // necessarily be accurate if the caller just updated the object but has
    // not saved it yet. That's a weird use case and would require more
    // gymnastics, so don't bother trying to get it totally correct for now.

    if ($this->getEvent()) {
      return $this->getEvent()->getNextEventEpoch();
    } else {
      return $this->getNextEventEpoch(null, $is_reschedule = false);
    }
  }


/* -(  PhabricatorDestructibleInterface  )----------------------------------- */


  public function destroyObjectPermanently(
    PhabricatorDestructionEngine $engine) {

    $this->openTransaction();
      queryfx(
        $this->establishConnection('w'),
        'DELETE FROM %T WHERE triggerID = %d',
        id(new PhabricatorWorkerTriggerEvent())->getTableName(),
        $this->getID());

      $this->delete();
    $this->saveTransaction();
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  // NOTE: Triggers are low-level infrastructure and do not have real
  // policies, but implementing the policy interface allows us to use
  // infrastructure like handles.

  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
    );
  }

  public function getPolicy($capability) {
    return PhabricatorPolicies::getMostOpenPolicy();
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return true;
  }

}
