<?php

final class NuanceItem
  extends NuanceDAO
  implements
    PhabricatorPolicyInterface,
    PhabricatorApplicationTransactionInterface {

  const STATUS_IMPORTING = 'importing';
  const STATUS_ROUTING = 'routing';
  const STATUS_OPEN = 'open';
  const STATUS_ASSIGNED = 'assigned';
  const STATUS_CLOSED = 'closed';

  protected $status;
  protected $ownerPHID;
  protected $requestorPHID;
  protected $sourcePHID;
  protected $queuePHID;
  protected $itemType;
  protected $itemKey;
  protected $itemContainerKey;
  protected $data = array();
  protected $mailKey;

  private $source = self::ATTACHABLE;
  private $implementation = self::ATTACHABLE;

  public static function initializeNewItem() {
    return id(new NuanceItem())
      ->setStatus(self::STATUS_OPEN);
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'data' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'ownerPHID' => 'phid?',
        'requestorPHID' => 'phid?',
        'queuePHID' => 'phid?',
        'itemType' => 'text64',
        'itemKey' => 'text64',
        'itemContainerKey' => 'text64?',
        'status' => 'text32',
        'mailKey' => 'bytes20',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_source' => array(
          'columns' => array('sourcePHID', 'status'),
        ),
        'key_owner' => array(
          'columns' => array('ownerPHID', 'status'),
        ),
        'key_requestor' => array(
          'columns' => array('requestorPHID', 'status'),
        ),
        'key_queue' => array(
          'columns' => array('queuePHID', 'status'),
        ),
        'key_container' => array(
          'columns' => array('sourcePHID', 'itemContainerKey'),
        ),
        'key_item' => array(
          'columns' => array('sourcePHID', 'itemKey'),
          'unique' => true,
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      NuanceItemPHIDType::TYPECONST);
  }

  public function save() {
    if (!$this->getMailKey()) {
      $this->setMailKey(Filesystem::readRandomCharacters(20));
    }
    return parent::save();
  }

  public function getURI() {
    return '/nuance/item/view/'.$this->getID().'/';
  }

  public function getSource() {
    return $this->assertAttached($this->source);
  }

  public function attachSource(NuanceSource $source) {
    $this->source = $source;
  }

  public function getItemProperty($key, $default = null) {
    return idx($this->data, $key, $default);
  }

  public function setItemProperty($key, $value) {
    $this->data[$key] = $value;
    return $this;
  }

  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
      PhabricatorPolicyCapability::CAN_EDIT,
    );
  }

  public function getPolicy($capability) {
    // TODO - this should be based on the queues the item currently resides in
    return PhabricatorPolicies::POLICY_USER;
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    // TODO - requestors should get auto access too!
    return $viewer->getPHID() == $this->ownerPHID;
  }

  public function describeAutomaticCapability($capability) {
    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        return pht('Owners of an item can always view it.');
      case PhabricatorPolicyCapability::CAN_EDIT:
        return pht('Owners of an item can always edit it.');
    }
    return null;
  }

  public function getDisplayName() {
    return $this->getImplementation()->getItemDisplayName($this);
  }

  public function scheduleUpdate() {
    PhabricatorWorker::scheduleTask(
      'NuanceItemUpdateWorker',
      array(
        'itemPHID' => $this->getPHID(),
      ),
      array(
        'objectPHID' => $this->getPHID(),
      ));
  }

  public function issueCommand(
    $author_phid,
    $command,
    array $parameters = array()) {

    $command = id(NuanceItemCommand::initializeNewCommand())
      ->setItemPHID($this->getPHID())
      ->setAuthorPHID($author_phid)
      ->setCommand($command)
      ->setParameters($parameters)
      ->save();

    $this->scheduleUpdate();

    return $this;
  }

  public function getImplementation() {
    return $this->assertAttached($this->implementation);
  }

  public function attachImplementation(NuanceItemType $type) {
    $this->implementation = $type;
    return $this;
  }


/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */


  public function getApplicationTransactionEditor() {
    return new NuanceItemEditor();
  }

  public function getApplicationTransactionObject() {
    return $this;
  }

  public function getApplicationTransactionTemplate() {
    return new NuanceItemTransaction();
  }

  public function willRenderTimeline(
    PhabricatorApplicationTransactionView $timeline,
    AphrontRequest $request) {
    return $timeline;
  }

}
