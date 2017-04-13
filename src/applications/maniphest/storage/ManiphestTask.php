<?php

final class ManiphestTask extends ManiphestDAO
  implements
    PhabricatorSubscribableInterface,
    PhabricatorMarkupInterface,
    PhabricatorPolicyInterface,
    PhabricatorTokenReceiverInterface,
    PhabricatorFlaggableInterface,
    PhabricatorMentionableInterface,
    PhrequentTrackableInterface,
    PhabricatorCustomFieldInterface,
    PhabricatorDestructibleInterface,
    PhabricatorApplicationTransactionInterface,
    PhabricatorProjectInterface,
    PhabricatorSpacesInterface,
    PhabricatorConduitResultInterface,
    PhabricatorFulltextInterface,
    DoorkeeperBridgedObjectInterface,
    PhabricatorEditEngineSubtypeInterface,
    PhabricatorEditEngineLockableInterface {

  const MARKUP_FIELD_DESCRIPTION = 'markup:desc';

  protected $authorPHID;
  protected $ownerPHID;

  protected $status;
  protected $priority;
  protected $subpriority = 0;

  protected $title = '';
  protected $originalTitle = '';
  protected $description = '';
  protected $originalEmailSource;
  protected $mailKey;
  protected $viewPolicy = PhabricatorPolicies::POLICY_USER;
  protected $editPolicy = PhabricatorPolicies::POLICY_USER;

  protected $ownerOrdering;
  protected $spacePHID;
  protected $bridgedObjectPHID;
  protected $properties = array();
  protected $points;
  protected $subtype;

  private $subscriberPHIDs = self::ATTACHABLE;
  private $groupByProjectPHID = self::ATTACHABLE;
  private $customFields = self::ATTACHABLE;
  private $edgeProjectPHIDs = self::ATTACHABLE;
  private $bridgedObject = self::ATTACHABLE;

  public static function initializeNewTask(PhabricatorUser $actor) {
    $app = id(new PhabricatorApplicationQuery())
      ->setViewer($actor)
      ->withClasses(array('PhabricatorManiphestApplication'))
      ->executeOne();

    $view_policy = $app->getPolicy(ManiphestDefaultViewCapability::CAPABILITY);
    $edit_policy = $app->getPolicy(ManiphestDefaultEditCapability::CAPABILITY);

    return id(new ManiphestTask())
      ->setStatus(ManiphestTaskStatus::getDefaultStatus())
      ->setPriority(ManiphestTaskPriority::getDefaultPriority())
      ->setAuthorPHID($actor->getPHID())
      ->setViewPolicy($view_policy)
      ->setEditPolicy($edit_policy)
      ->setSpacePHID($actor->getDefaultSpacePHID())
      ->setSubtype(PhabricatorEditEngineSubtype::SUBTYPE_DEFAULT)
      ->attachProjectPHIDs(array())
      ->attachSubscriberPHIDs(array());
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'properties' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'ownerPHID' => 'phid?',
        'status' => 'text12',
        'priority' => 'uint32',
        'title' => 'sort',
        'originalTitle' => 'text',
        'description' => 'text',
        'mailKey' => 'bytes20',
        'ownerOrdering' => 'text64?',
        'originalEmailSource' => 'text255?',
        'subpriority' => 'double',
        'points' => 'double?',
        'bridgedObjectPHID' => 'phid?',
        'subtype' => 'text64',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_phid' => null,
        'phid' => array(
          'columns' => array('phid'),
          'unique' => true,
        ),
        'priority' => array(
          'columns' => array('priority', 'status'),
        ),
        'status' => array(
          'columns' => array('status'),
        ),
        'ownerPHID' => array(
          'columns' => array('ownerPHID', 'status'),
        ),
        'authorPHID' => array(
          'columns' => array('authorPHID', 'status'),
        ),
        'ownerOrdering' => array(
          'columns' => array('ownerOrdering'),
        ),
        'priority_2' => array(
          'columns' => array('priority', 'subpriority'),
        ),
        'key_dateCreated' => array(
          'columns' => array('dateCreated'),
        ),
        'key_dateModified' => array(
          'columns' => array('dateModified'),
        ),
        'key_title' => array(
          'columns' => array('title(64)'),
        ),
        'key_bridgedobject' => array(
          'columns' => array('bridgedObjectPHID'),
          'unique' => true,
        ),
        'key_subtype' => array(
          'columns' => array('subtype'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function loadDependsOnTaskPHIDs() {
    return PhabricatorEdgeQuery::loadDestinationPHIDs(
      $this->getPHID(),
      ManiphestTaskDependsOnTaskEdgeType::EDGECONST);
  }

  public function loadDependedOnByTaskPHIDs() {
    return PhabricatorEdgeQuery::loadDestinationPHIDs(
      $this->getPHID(),
      ManiphestTaskDependedOnByTaskEdgeType::EDGECONST);
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(ManiphestTaskPHIDType::TYPECONST);
  }

  public function getSubscriberPHIDs() {
    return $this->assertAttached($this->subscriberPHIDs);
  }

  public function getProjectPHIDs() {
    return $this->assertAttached($this->edgeProjectPHIDs);
  }

  public function attachProjectPHIDs(array $phids) {
    $this->edgeProjectPHIDs = $phids;
    return $this;
  }

  public function attachSubscriberPHIDs(array $phids) {
    $this->subscriberPHIDs = $phids;
    return $this;
  }

  public function setOwnerPHID($phid) {
    $this->ownerPHID = nonempty($phid, null);
    return $this;
  }

  public function setTitle($title) {
    $this->title = $title;
    if (!$this->getID()) {
      $this->originalTitle = $title;
    }
    return $this;
  }

  public function getMonogram() {
    return 'T'.$this->getID();
  }

  public function getURI() {
    return '/'.$this->getMonogram();
  }

  public function attachGroupByProjectPHID($phid) {
    $this->groupByProjectPHID = $phid;
    return $this;
  }

  public function getGroupByProjectPHID() {
    return $this->assertAttached($this->groupByProjectPHID);
  }

  public function save() {
    if (!$this->mailKey) {
      $this->mailKey = Filesystem::readRandomCharacters(20);
    }

    $result = parent::save();

    return $result;
  }

  public function isClosed() {
    return ManiphestTaskStatus::isClosedStatus($this->getStatus());
  }

  public function isLocked() {
    return ManiphestTaskStatus::isLockedStatus($this->getStatus());
  }

  public function setProperty($key, $value) {
    $this->properties[$key] = $value;
    return $this;
  }

  public function getProperty($key, $default = null) {
    return idx($this->properties, $key, $default);
  }

  public function getCoverImageFilePHID() {
    return idx($this->properties, 'cover.filePHID');
  }

  public function getCoverImageThumbnailPHID() {
    return idx($this->properties, 'cover.thumbnailPHID');
  }

  public function getWorkboardOrderVectors() {
    return array(
      PhabricatorProjectColumn::ORDER_PRIORITY => array(
        (int)-$this->getPriority(),
        (double)-$this->getSubpriority(),
        (int)-$this->getID(),
      ),
    );
  }

  private function comparePriorityTo(ManiphestTask $other) {
    $upri = $this->getPriority();
    $vpri = $other->getPriority();

    if ($upri != $vpri) {
      return ($upri - $vpri);
    }

    $usub = $this->getSubpriority();
    $vsub = $other->getSubpriority();

    if ($usub != $vsub) {
      return ($usub - $vsub);
    }

    $uid = $this->getID();
    $vid = $other->getID();

    if ($uid != $vid) {
      return ($uid - $vid);
    }

    return 0;
  }

  public function isLowerPriorityThan(ManiphestTask $other) {
    return ($this->comparePriorityTo($other) < 0);
  }

  public function isHigherPriorityThan(ManiphestTask $other) {
    return ($this->comparePriorityTo($other) > 0);
  }

  public function getWorkboardProperties() {
    return array(
      'status' => $this->getStatus(),
      'points' => (double)$this->getPoints(),
    );
  }


/* -(  PhabricatorSubscribableInterface  )----------------------------------- */


  public function isAutomaticallySubscribed($phid) {
    return ($phid == $this->getOwnerPHID());
  }


/* -(  Markup Interface  )--------------------------------------------------- */


  /**
   * @task markup
   */
  public function getMarkupFieldKey($field) {
    $content = $this->getMarkupText($field);
    return PhabricatorMarkupEngine::digestRemarkupContent($this, $content);
  }


  /**
   * @task markup
   */
  public function getMarkupText($field) {
    return $this->getDescription();
  }


  /**
   * @task markup
   */
  public function newMarkupEngine($field) {
    return PhabricatorMarkupEngine::newManiphestMarkupEngine();
  }


  /**
   * @task markup
   */
  public function didMarkupText(
    $field,
    $output,
    PhutilMarkupEngine $engine) {
    return $output;
  }


  /**
   * @task markup
   */
  public function shouldUseMarkupCache($field) {
    return (bool)$this->getID();
  }


/* -(  Policy Interface  )--------------------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
      PhabricatorPolicyCapability::CAN_INTERACT,
      PhabricatorPolicyCapability::CAN_EDIT,
    );
  }

  public function getPolicy($capability) {
    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        return $this->getViewPolicy();
      case PhabricatorPolicyCapability::CAN_INTERACT:
        if ($this->isLocked()) {
          return PhabricatorPolicies::POLICY_NOONE;
        } else {
          return $this->getViewPolicy();
        }
      case PhabricatorPolicyCapability::CAN_EDIT:
        return $this->getEditPolicy();
    }
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $user) {
    // The owner of a task can always view and edit it.
    $owner_phid = $this->getOwnerPHID();
    if ($owner_phid) {
      $user_phid = $user->getPHID();
      if ($user_phid == $owner_phid) {
        return true;
      }
    }

    return false;
  }

  public function describeAutomaticCapability($capability) {
    return pht('The owner of a task can always view and edit it.');
  }


/* -(  PhabricatorTokenReceiverInterface  )---------------------------------- */


  public function getUsersToNotifyOfTokenGiven() {
    // Sort of ambiguous who this was intended for; just let them both know.
    return array_filter(
      array_unique(
        array(
          $this->getAuthorPHID(),
          $this->getOwnerPHID(),
        )));
  }


/* -(  PhabricatorCustomFieldInterface  )------------------------------------ */


  public function getCustomFieldSpecificationForRole($role) {
    return PhabricatorEnv::getEnvConfig('maniphest.fields');
  }

  public function getCustomFieldBaseClass() {
    return 'ManiphestCustomField';
  }

  public function getCustomFields() {
    return $this->assertAttached($this->customFields);
  }

  public function attachCustomFields(PhabricatorCustomFieldAttachment $fields) {
    $this->customFields = $fields;
    return $this;
  }


/* -(  PhabricatorDestructibleInterface  )----------------------------------- */


  public function destroyObjectPermanently(
    PhabricatorDestructionEngine $engine) {

    $this->openTransaction();
    $this->delete();
    $this->saveTransaction();
  }


/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */


  public function getApplicationTransactionEditor() {
    return new ManiphestTransactionEditor();
  }

  public function getApplicationTransactionObject() {
    return $this;
  }

  public function getApplicationTransactionTemplate() {
    return new ManiphestTransaction();
  }

  public function willRenderTimeline(
    PhabricatorApplicationTransactionView $timeline,
    AphrontRequest $request) {

    return $timeline;
  }


/* -(  PhabricatorSpacesInterface  )----------------------------------------- */


  public function getSpacePHID() {
    return $this->spacePHID;
  }


/* -(  PhabricatorConduitResultInterface  )---------------------------------- */


  public function getFieldSpecificationsForConduit() {
    return array(
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('title')
        ->setType('string')
        ->setDescription(pht('The title of the task.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('description')
        ->setType('remarkup')
        ->setDescription(pht('The task description.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('authorPHID')
        ->setType('phid')
        ->setDescription(pht('Original task author.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('ownerPHID')
        ->setType('phid?')
        ->setDescription(pht('Current task owner, if task is assigned.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('status')
        ->setType('map<string, wild>')
        ->setDescription(pht('Information about task status.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('priority')
        ->setType('map<string, wild>')
        ->setDescription(pht('Information about task priority.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('points')
        ->setType('points')
        ->setDescription(pht('Point value of the task.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('subtype')
        ->setType('string')
        ->setDescription(pht('Subtype of the task.')),
    );
  }

  public function getFieldValuesForConduit() {
    $status_value = $this->getStatus();
    $status_info = array(
      'value' => $status_value,
      'name' => ManiphestTaskStatus::getTaskStatusName($status_value),
      'color' => ManiphestTaskStatus::getStatusColor($status_value),
    );

    $priority_value = (int)$this->getPriority();
    $priority_info = array(
      'value' => $priority_value,
      'subpriority' => (double)$this->getSubpriority(),
      'name' => ManiphestTaskPriority::getTaskPriorityName($priority_value),
      'color' => ManiphestTaskPriority::getTaskPriorityColor($priority_value),
    );

    return array(
      'name' => $this->getTitle(),
      'description' => array(
        'raw' => $this->getDescription(),
      ),
      'authorPHID' => $this->getAuthorPHID(),
      'ownerPHID' => $this->getOwnerPHID(),
      'status' => $status_info,
      'priority' => $priority_info,
      'points' => $this->getPoints(),
      'subtype' => $this->getSubtype(),
    );
  }

  public function getConduitSearchAttachments() {
    return array(
      id(new PhabricatorBoardColumnsSearchEngineAttachment())
        ->setAttachmentKey('columns'),
    );
  }


/* -(  PhabricatorFulltextInterface  )--------------------------------------- */


  public function newFulltextEngine() {
    return new ManiphestTaskFulltextEngine();
  }


/* -(  DoorkeeperBridgedObjectInterface  )----------------------------------- */


  public function getBridgedObject() {
    return $this->assertAttached($this->bridgedObject);
  }

  public function attachBridgedObject(
    DoorkeeperExternalObject $object = null) {
    $this->bridgedObject = $object;
    return $this;
  }


/* -(  PhabricatorEditEngineSubtypeInterface  )------------------------------ */


  public function getEditEngineSubtype() {
    return $this->getSubtype();
  }

  public function setEditEngineSubtype($value) {
    return $this->setSubtype($value);
  }

  public function newEditEngineSubtypeMap() {
    $config = PhabricatorEnv::getEnvConfig('maniphest.subtypes');
    return PhabricatorEditEngineSubtype::newSubtypeMap($config);
  }


/* -(  PhabricatorEditEngineLockableInterface  )----------------------------- */


  public function newEditEngineLock() {
    return new ManiphestTaskEditEngineLock();
  }

}
