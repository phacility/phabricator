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
    PhabricatorFerretInterface,
    DoorkeeperBridgedObjectInterface,
    PhabricatorEditEngineSubtypeInterface,
    PhabricatorEditEngineLockableInterface,
    PhabricatorEditEngineMFAInterface,
    PhabricatorPolicyCodexInterface,
    PhabricatorUnlockableInterface {

  protected $authorPHID;
  protected $ownerPHID;

  protected $status;
  protected $priority;
  protected $subpriority = 0;

  protected $title = '';
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

  protected $closedEpoch;
  protected $closerPHID;

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
        'status' => 'text64',
        'priority' => 'uint32',
        'title' => 'sort',
        'description' => 'text',
        'mailKey' => 'bytes20',
        'ownerOrdering' => 'text64?',
        'originalEmailSource' => 'text255?',
        'subpriority' => 'double',
        'points' => 'double?',
        'bridgedObjectPHID' => 'phid?',
        'subtype' => 'text64',
        'closedEpoch' => 'epoch?',
        'closerPHID' => 'phid?',
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
        'key_closed' => array(
          'columns' => array('closedEpoch'),
        ),
        'key_closer' => array(
          'columns' => array('closerPHID', 'closedEpoch'),
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

  public function areCommentsLocked() {
    if ($this->areEditsLocked()) {
      return true;
    }

    return ManiphestTaskStatus::areCommentsLockedInStatus($this->getStatus());
  }

  public function areEditsLocked() {
    return ManiphestTaskStatus::areEditsLockedInStatus($this->getStatus());
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

  public function getPriorityKeyword() {
    $priority = $this->getPriority();

    $keyword = ManiphestTaskPriority::getKeywordForTaskPriority($priority);
    if ($keyword !== null) {
      return $keyword;
    }

    return ManiphestTaskPriority::UNKNOWN_PRIORITY_KEYWORD;
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
        if ($this->areCommentsLocked()) {
          return PhabricatorPolicies::POLICY_NOONE;
        } else {
          return $this->getViewPolicy();
        }
      case PhabricatorPolicyCapability::CAN_EDIT:
        if ($this->areEditsLocked()) {
          return PhabricatorPolicies::POLICY_NOONE;
        } else {
          return $this->getEditPolicy();
        }
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

  public function getApplicationTransactionTemplate() {
    return new ManiphestTransaction();
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
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('closerPHID')
        ->setType('phid?')
        ->setDescription(
          pht('User who closed the task, if the task is closed.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('dateClosed')
        ->setType('int?')
        ->setDescription(
          pht('Epoch timestamp when the task was closed.')),
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
      'name' => ManiphestTaskPriority::getTaskPriorityName($priority_value),
      'color' => ManiphestTaskPriority::getTaskPriorityColor($priority_value),
    );

    $closed_epoch = $this->getClosedEpoch();
    if ($closed_epoch !== null) {
      $closed_epoch = (int)$closed_epoch;
    }

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
      'closerPHID' => $this->getCloserPHID(),
      'dateClosed' => $closed_epoch,
    );
  }

  public function getConduitSearchAttachments() {
    return array(
      id(new PhabricatorBoardColumnsSearchEngineAttachment())
        ->setAttachmentKey('columns'),
    );
  }

  public function newSubtypeObject() {
    $subtype_key = $this->getEditEngineSubtype();
    $subtype_map = $this->newEditEngineSubtypeMap();
    return $subtype_map->getSubtype($subtype_key);
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
    return PhabricatorEditEngineSubtype::newSubtypeMap($config)
      ->setDatasource(new ManiphestTaskSubtypeDatasource());
  }


/* -(  PhabricatorEditEngineLockableInterface  )----------------------------- */


  public function newEditEngineLock() {
    return new ManiphestTaskEditEngineLock();
  }


/* -(  PhabricatorFerretInterface  )----------------------------------------- */


  public function newFerretEngine() {
    return new ManiphestTaskFerretEngine();
  }


/* -(  PhabricatorEditEngineMFAInterface  )---------------------------------- */


  public function newEditEngineMFAEngine() {
    return new ManiphestTaskMFAEngine();
  }


/* -(  PhabricatorPolicyCodexInterface  )------------------------------------ */


  public function newPolicyCodex() {
    return new ManiphestTaskPolicyCodex();
  }


/* -(  PhabricatorUnlockableInterface  )------------------------------------- */


  public function newUnlockEngine() {
    return new ManiphestTaskUnlockEngine();
  }

}
