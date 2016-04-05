<?php

final class PhabricatorBadgesBadge extends PhabricatorBadgesDAO
  implements
    PhabricatorPolicyInterface,
    PhabricatorApplicationTransactionInterface,
    PhabricatorSubscribableInterface,
    PhabricatorTokenReceiverInterface,
    PhabricatorFlaggableInterface,
    PhabricatorDestructibleInterface,
    PhabricatorConduitResultInterface {

  protected $name;
  protected $flavor;
  protected $description;
  protected $icon;
  protected $quality;
  protected $mailKey;
  protected $editPolicy;
  protected $status;
  protected $creatorPHID;

  private $awards = self::ATTACHABLE;

  const STATUS_ACTIVE = 'open';
  const STATUS_ARCHIVED = 'closed';

  const DEFAULT_ICON = 'fa-star';

  public static function getStatusNameMap() {
    return array(
      self::STATUS_ACTIVE => pht('Active'),
      self::STATUS_ARCHIVED => pht('Archived'),
    );
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
      ->setQuality(PhabricatorBadgesQuality::DEFAULT_QUALITY)
      ->setCreatorPHID($actor->getPHID())
      ->setEditPolicy($edit_policy)
      ->setFlavor('')
      ->setDescription('')
      ->setStatus(self::STATUS_ACTIVE);
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_COLUMN_SCHEMA => array(
        'name' => 'text255',
        'flavor' => 'text255',
        'description' => 'text',
        'icon' => 'text255',
        'quality' => 'uint32',
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

  public function isArchived() {
    return ($this->getStatus() == self::STATUS_ARCHIVED);
  }

  public function attachAwards(array $awards) {
    $this->awards = $awards;
    return $this;
  }

  public function getAwards() {
    return $this->assertAttached($this->awards);
  }

  public function getViewURI() {
    return '/badges/view/'.$this->getID().'/';
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


/* -(  PhabricatorTokenReceiverInterface  )---------------------------------- */


  public function getUsersToNotifyOfTokenGiven() {
    return array($this->getCreatorPHID());
  }



/* -(  PhabricatorDestructibleInterface  )----------------------------------- */


  public function destroyObjectPermanently(
    PhabricatorDestructionEngine $engine) {

    $awards = id(new PhabricatorBadgesAwardQuery())
      ->setViewer($engine->getViewer())
      ->withBadgePHIDs(array($this->getPHID()))
      ->execute();

    foreach ($awards as $award) {
      $engine->destroyObjectPermanently($award);
    }

    $this->openTransaction();
      $this->delete();
    $this->saveTransaction();
  }

/* -(  PhabricatorConduitResultInterface  )---------------------------------- */


  public function getFieldSpecificationsForConduit() {
    return array(
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('name')
        ->setType('string')
        ->setDescription(pht('The name of the badge.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('creatorPHID')
        ->setType('phid')
        ->setDescription(pht('User PHID of the creator.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('status')
        ->setType('string')
        ->setDescription(pht('Active or archived status of the badge.')),
    );
  }

  public function getFieldValuesForConduit() {
    return array(
      'name' => $this->getName(),
      'creatorPHID' => $this->getCreatorPHID(),
      'status' => $this->getStatus(),
    );
  }

  public function getConduitSearchAttachments() {
    return array();
  }

}
