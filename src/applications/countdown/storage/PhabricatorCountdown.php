<?php

final class PhabricatorCountdown extends PhabricatorCountdownDAO
  implements
    PhabricatorPolicyInterface,
    PhabricatorFlaggableInterface,
    PhabricatorSubscribableInterface,
    PhabricatorApplicationTransactionInterface,
    PhabricatorTokenReceiverInterface,
    PhabricatorSpacesInterface,
    PhabricatorProjectInterface,
    PhabricatorDestructibleInterface,
    PhabricatorConduitResultInterface {

  protected $title;
  protected $authorPHID;
  protected $epoch;
  protected $description;
  protected $viewPolicy;
  protected $editPolicy;
  protected $mailKey;
  protected $spacePHID;

  public static function initializeNewCountdown(PhabricatorUser $actor) {
    $app = id(new PhabricatorApplicationQuery())
      ->setViewer($actor)
      ->withClasses(array('PhabricatorCountdownApplication'))
      ->executeOne();

    $view_policy = $app->getPolicy(
      PhabricatorCountdownDefaultViewCapability::CAPABILITY);

    $edit_policy = $app->getPolicy(
      PhabricatorCountdownDefaultEditCapability::CAPABILITY);

    return id(new PhabricatorCountdown())
      ->setAuthorPHID($actor->getPHID())
      ->setViewPolicy($view_policy)
      ->setEditPolicy($edit_policy)
      ->setSpacePHID($actor->getDefaultSpacePHID());
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_COLUMN_SCHEMA => array(
        'title' => 'text255',
        'description' => 'text',
        'mailKey' => 'bytes20',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_epoch' => array(
          'columns' => array('epoch'),
        ),
        'key_author' => array(
          'columns' => array('authorPHID', 'epoch'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorCountdownCountdownPHIDType::TYPECONST);
  }

  public function getMonogram() {
    return 'C'.$this->getID();
  }

  public function getURI() {
    return '/'.$this->getMonogram();
  }

  public function save() {
    if (!$this->getMailKey()) {
      $this->setMailKey(Filesystem::readRandomCharacters(20));
    }
    return parent::save();
  }


/* -(  PhabricatorSubscribableInterface  )----------------------------------- */


  public function isAutomaticallySubscribed($phid) {
    return ($phid == $this->getAuthorPHID());
  }


/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */


  public function getApplicationTransactionEditor() {
    return new PhabricatorCountdownEditor();
  }

  public function getApplicationTransactionObject() {
    return $this;
  }

  public function getApplicationTransactionTemplate() {
    return new PhabricatorCountdownTransaction();
  }

  public function willRenderTimeline(
    PhabricatorApplicationTransactionView $timeline,
    AphrontRequest $request) {

    return $timeline;
  }

/* -(  PhabricatorTokenReceiverInterface  )---------------------------------- */


  public function getUsersToNotifyOfTokenGiven() {
    return array($this->getAuthorPHID());
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

/* -( PhabricatorSpacesInterface )------------------------------------------- */


  public function getSpacePHID() {
    return $this->spacePHID;
  }

/* -(  PhabricatorDestructibleInterface  )----------------------------------- */


  public function destroyObjectPermanently(
      PhabricatorDestructionEngine $engine) {

    $this->openTransaction();
    $this->delete();
    $this->saveTransaction();
  }

/* -(  PhabricatorConduitResultInterface  )---------------------------------- */


  public function getFieldSpecificationsForConduit() {
    return array(
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('title')
        ->setType('string')
        ->setDescription(pht('The title of the countdown.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('description')
        ->setType('remarkup')
        ->setDescription(pht('The description of the countdown.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('epoch')
        ->setType('epoch')
        ->setDescription(pht('The end date of the countdown.')),
    );
  }

  public function getFieldValuesForConduit() {
    return array(
      'title' => $this->getTitle(),
      'description' => array(
        'raw' => $this->getDescription(),
      ),
      'epoch' => (int)$this->getEpoch(),
    );
  }

  public function getConduitSearchAttachments() {
    return array();
  }

}
