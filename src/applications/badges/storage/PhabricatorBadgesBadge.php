<?php

final class PhabricatorBadgesBadge extends PhabricatorBadgesDAO
  implements
    PhabricatorPolicyInterface,
    PhabricatorApplicationTransactionInterface,
    PhabricatorSubscribableInterface,
    PhabricatorTokenReceiverInterface,
    PhabricatorFlaggableInterface,
    PhabricatorDestructibleInterface {

  protected $name;
  protected $flavor;
  protected $description;
  protected $icon;
  protected $quality;
  protected $mailKey;
  protected $editPolicy;
  protected $status;
  protected $creatorPHID;

  private $recipientPHIDs = self::ATTACHABLE;

  const STATUS_OPEN = 'open';
  const STATUS_CLOSED = 'closed';

  const DEFAULT_ICON = 'fa-star';
  const DEFAULT_QUALITY = 'green';

  const POOR = 'grey';
  const COMMON = 'white';
  const UNCOMMON = 'green';
  const RARE = 'blue';
  const EPIC = 'indigo';
  const LEGENDARY = 'orange';
  const HEIRLOOM = 'yellow';

  public static function getStatusNameMap() {
    return array(
      self::STATUS_OPEN => pht('Active'),
      self::STATUS_CLOSED => pht('Archived'),
    );
  }

  public static function getQualityNameMap() {
    return array(
      self::POOR => pht('Poor'),
      self::COMMON => pht('Common'),
      self::UNCOMMON => pht('Uncommon'),
      self::RARE => pht('Rare'),
      self::EPIC => pht('Epic'),
      self::LEGENDARY => pht('Legendary'),
      self::HEIRLOOM => pht('Heirloom'),
    );
  }

  public static function getIconNameMap() {
    return PhabricatorBadgesIcon::getIconMap();
  }

  public static function initializeNewBadge(PhabricatorUser $actor) {
    $app = id(new PhabricatorApplicationQuery())
      ->setViewer($actor)
      ->withClasses(array('PhabricatorBadgesApplication'))
      ->executeOne();

    $view_policy = PhabricatorPolicies::getMostOpenPolicy();

    $edit_policy =
      $app->getPolicy(PhabricatorBadgesDefaultEditCapability::CAPABILITY);

    return id(new PhabricatorBadgesBadge())
      ->setIcon(self::DEFAULT_ICON)
      ->setQuality(self::DEFAULT_QUALITY)
      ->setCreatorPHID($actor->getPHID())
      ->setEditPolicy($edit_policy)
      ->setStatus(self::STATUS_OPEN);
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_COLUMN_SCHEMA => array(
        'name' => 'text255',
        'flavor' => 'text255',
        'description' => 'text',
        'icon' => 'text255',
        'quality' => 'text255',
        'status' => 'text32',
        'mailKey' => 'bytes20',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_creator' => array(
          'columns' => array('creatorPHID', 'dateModified'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return
      PhabricatorPHID::generateNewPHID(PhabricatorBadgesPHIDType::TYPECONST);
  }

  public function isClosed() {
    return ($this->getStatus() == self::STATUS_CLOSED);
  }

  public function attachRecipientPHIDs(array $phids) {
    $this->recipientPHIDs = $phids;
    return $this;
  }

  public function getRecipientPHIDs() {
    return $this->assertAttached($this->recipientPHIDs);
  }

  public function save() {
    if (!$this->getMailKey()) {
      $this->setMailKey(Filesystem::readRandomCharacters(20));
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
        return PhabricatorPolicies::getMostOpenPolicy();
      case PhabricatorPolicyCapability::CAN_EDIT:
        return $this->getEditPolicy();
    }
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return false;
  }

  public function describeAutomaticCapability($capability) {
    return null;
  }


/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */


  public function getApplicationTransactionEditor() {
    return new PhabricatorBadgesEditor();
  }

  public function getApplicationTransactionObject() {
    return $this;
  }

  public function getApplicationTransactionTemplate() {
    return new PhabricatorBadgesTransaction();
  }

  public function willRenderTimeline(
    PhabricatorApplicationTransactionView $timeline,
    AphrontRequest $request) {

    return $timeline;
  }


/* -(  PhabricatorSubscribableInterface  )----------------------------------- */


  public function isAutomaticallySubscribed($phid) {
    return ($this->creatorPHID == $phid);
  }

  public function shouldShowSubscribersProperty() {
    return true;
  }

  public function shouldAllowSubscription($phid) {
    return true;
  }


/* -(  PhabricatorTokenReceiverInterface  )---------------------------------- */


  public function getUsersToNotifyOfTokenGiven() {
    return array($this->getCreatorPHID());
  }



/* -(  PhabricatorDestructibleInterface  )----------------------------------- */


  public function destroyObjectPermanently(
    PhabricatorDestructionEngine $engine) {

    $this->openTransaction();
      $this->delete();
    $this->saveTransaction();
  }

}
