<?php

final class PhabricatorCalendarImport
  extends PhabricatorCalendarDAO
  implements
    PhabricatorPolicyInterface,
    PhabricatorApplicationTransactionInterface,
    PhabricatorDestructibleInterface {

  protected $name;
  protected $authorPHID;
  protected $viewPolicy;
  protected $editPolicy;
  protected $engineType;
  protected $parameters = array();
  protected $isDisabled = 0;
  protected $triggerPHID;
  protected $triggerFrequency;

  const FREQUENCY_ONCE = 'once';
  const FREQUENCY_HOURLY = 'hourly';
  const FREQUENCY_DAILY = 'daily';

  private $engine = self::ATTACHABLE;

  public static function initializeNewCalendarImport(
    PhabricatorUser $actor,
    PhabricatorCalendarImportEngine $engine) {
    return id(new self())
      ->setName('')
      ->setAuthorPHID($actor->getPHID())
      ->setViewPolicy($actor->getPHID())
      ->setEditPolicy($actor->getPHID())
      ->setIsDisabled(0)
      ->setEngineType($engine->getImportEngineType())
      ->attachEngine($engine)
      ->setTriggerFrequency(self::FREQUENCY_ONCE);
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'parameters' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'name' => 'text',
        'engineType' => 'text64',
        'isDisabled' => 'bool',
        'triggerPHID' => 'phid?',
        'triggerFrequency' => 'text64',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_author' => array(
          'columns' => array('authorPHID'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function getPHIDType() {
    return PhabricatorCalendarImportPHIDType::TYPECONST;
  }

  public function getURI() {
    $id = $this->getID();
    return "/calendar/import/{$id}/";
  }

  public function attachEngine(PhabricatorCalendarImportEngine $engine) {
    $this->engine = $engine;
    return $this;
  }

  public function getEngine() {
    return $this->assertAttached($this->engine);
  }

  public function getParameter($key, $default = null) {
    return idx($this->parameters, $key, $default);
  }

  public function setParameter($key, $value) {
    $this->parameters[$key] = $value;
    return $this;
  }

  public function getDisplayName() {
    $name = $this->getName();
    if (strlen($name)) {
      return $name;
    }

    return $this->getEngine()->getDisplayName($this);
  }

  public static function getTriggerFrequencyMap() {
    return array(
      self::FREQUENCY_ONCE => array(
        'name' => pht('No Automatic Updates'),
      ),
      self::FREQUENCY_HOURLY => array(
        'name' => pht('Update Hourly'),
      ),
      self::FREQUENCY_DAILY => array(
        'name' => pht('Update Daily'),
      ),
    );
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
      PhabricatorPolicyCapability::CAN_EDIT,
    );
  }

  public function getPolicy($capability) {
    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        return $this->getViewPolicy();
      case PhabricatorPolicyCapability::CAN_EDIT:
        return $this->getEditPolicy();
    }
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return false;
  }


/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */


  public function getApplicationTransactionEditor() {
    return new PhabricatorCalendarImportEditor();
  }

  public function getApplicationTransactionObject() {
    return $this;
  }

  public function getApplicationTransactionTemplate() {
    return new PhabricatorCalendarImportTransaction();
  }

  public function willRenderTimeline(
    PhabricatorApplicationTransactionView $timeline,
    AphrontRequest $request) {
    return $timeline;
  }

  public function newLogMessage($type, array $parameters) {
    $parameters = array(
      'type' => $type,
    ) + $parameters;

    return id(new PhabricatorCalendarImportLog())
      ->setImportPHID($this->getPHID())
      ->setParameters($parameters)
      ->save();
  }


/* -(  PhabricatorDestructibleInterface  )----------------------------------- */


  public function destroyObjectPermanently(
    PhabricatorDestructionEngine $engine) {
    $viewer = $engine->getViewer();

    $this->openTransaction();

      $trigger_phid = $this->getTriggerPHID();
      if ($trigger_phid) {
        $trigger = id(new PhabricatorWorkerTriggerQuery())
          ->setViewer($viewer)
          ->withPHIDs(array($trigger_phid))
          ->executeOne();
        if ($trigger) {
          $engine->destroyObject($trigger);
        }
      }

      $events = id(new PhabricatorCalendarEventQuery())
        ->setViewer($viewer)
        ->withImportSourcePHIDs(array($this->getPHID()))
        ->execute();
      foreach ($events as $event) {
        $engine->destroyObject($event);
      }

      $logs = id(new PhabricatorCalendarImportLogQuery())
        ->setViewer($viewer)
        ->withImportPHIDs(array($this->getPHID()))
        ->execute();
      foreach ($logs as $log) {
        $engine->destroyObject($log);
      }

      $this->delete();
    $this->saveTransaction();
  }

}
