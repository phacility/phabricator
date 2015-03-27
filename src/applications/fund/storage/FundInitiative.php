<?php

final class FundInitiative extends FundDAO
  implements
    PhabricatorPolicyInterface,
    PhabricatorProjectInterface,
    PhabricatorApplicationTransactionInterface,
    PhabricatorSubscribableInterface,
    PhabricatorMentionableInterface,
    PhabricatorFlaggableInterface,
    PhabricatorTokenReceiverInterface,
    PhabricatorDestructibleInterface {

  protected $name;
  protected $ownerPHID;
  protected $merchantPHID;
  protected $description;
  protected $risks;
  protected $viewPolicy;
  protected $editPolicy;
  protected $status;
  protected $totalAsCurrency;
  protected $mailKey;

  private $projectPHIDs = self::ATTACHABLE;

  const STATUS_OPEN = 'open';
  const STATUS_CLOSED = 'closed';

  public static function getStatusNameMap() {
    return array(
      self::STATUS_OPEN => pht('Open'),
      self::STATUS_CLOSED => pht('Closed'),
    );
  }

  public static function initializeNewInitiative(PhabricatorUser $actor) {
    $app = id(new PhabricatorApplicationQuery())
      ->setViewer($actor)
      ->withClasses(array('PhabricatorFundApplication'))
      ->executeOne();

    $view_policy = $app->getPolicy(FundDefaultViewCapability::CAPABILITY);

    return id(new FundInitiative())
      ->setOwnerPHID($actor->getPHID())
      ->setViewPolicy($view_policy)
      ->setEditPolicy($actor->getPHID())
      ->setStatus(self::STATUS_OPEN)
      ->setTotalAsCurrency(PhortuneCurrency::newEmptyCurrency());
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_COLUMN_SCHEMA => array(
        'name' => 'text255',
        'description' => 'text',
        'risks' => 'text',
        'status' => 'text32',
        'merchantPHID' => 'phid?',
        'totalAsCurrency' => 'text64',
        'mailKey' => 'bytes20',
      ),
      self::CONFIG_APPLICATION_SERIALIZERS => array(
        'totalAsCurrency' => new PhortuneCurrencySerializer(),
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_status' => array(
          'columns' => array('status'),
        ),
        'key_owner' => array(
          'columns' => array('ownerPHID'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(FundInitiativePHIDType::TYPECONST);
  }

  public function getMonogram() {
    return 'I'.$this->getID();
  }

  public function getProjectPHIDs() {
    return $this->assertAttached($this->projectPHIDs);
  }

  public function attachProjectPHIDs(array $phids) {
    $this->projectPHIDs = $phids;
    return $this;
  }

  public function isClosed() {
    return ($this->getStatus() == self::STATUS_CLOSED);
  }

  public function save() {
    if (!$this->mailKey) {
      $this->mailKey = Filesystem::readRandomCharacters(20);
    }
    return parent::save();
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
    if ($viewer->getPHID() == $this->getOwnerPHID()) {
      return true;
    }

    if ($capability == PhabricatorPolicyCapability::CAN_VIEW) {
      foreach ($viewer->getAuthorities() as $authority) {
        if ($authority instanceof PhortuneMerchant) {
          if ($authority->getPHID() == $this->getMerchantPHID()) {
            return true;
          }
        }
      }
    }

    return false;
  }

  public function describeAutomaticCapability($capability) {
    return pht(
      'The owner of an initiative can always view and edit it.');
  }


/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */


  public function getApplicationTransactionEditor() {
    return new FundInitiativeEditor();
  }

  public function getApplicationTransactionObject() {
    return $this;
  }

  public function getApplicationTransactionTemplate() {
    return new FundInitiativeTransaction();
  }

  public function willRenderTimeline(
    PhabricatorApplicationTransactionView $timeline,
    AphrontRequest $request) {

    return $timeline;
  }


/* -(  PhabricatorSubscribableInterface  )----------------------------------- */


  public function isAutomaticallySubscribed($phid) {
    return ($phid == $this->getOwnerPHID());
  }

  public function shouldShowSubscribersProperty() {
    return true;
  }

  public function shouldAllowSubscription($phid) {
    return true;
  }


/* -(  PhabricatorTokenRecevierInterface  )---------------------------------- */


  public function getUsersToNotifyOfTokenGiven() {
    return array(
      $this->getOwnerPHID(),
    );
  }


/* -(  PhabricatorDestructibleInterface  )----------------------------------- */


  public function destroyObjectPermanently(
    PhabricatorDestructionEngine $engine) {

    $this->openTransaction();
      $this->delete();
    $this->saveTransaction();
  }

}
