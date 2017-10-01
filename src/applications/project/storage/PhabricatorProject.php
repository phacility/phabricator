<?php

final class PhabricatorProject extends PhabricatorProjectDAO
  implements
    PhabricatorApplicationTransactionInterface,
    PhabricatorFlaggableInterface,
    PhabricatorPolicyInterface,
    PhabricatorExtendedPolicyInterface,
    PhabricatorCustomFieldInterface,
    PhabricatorDestructibleInterface,
    PhabricatorFulltextInterface,
    PhabricatorFerretInterface,
    PhabricatorConduitResultInterface,
    PhabricatorColumnProxyInterface {

  protected $name;
  protected $status = PhabricatorProjectStatus::STATUS_ACTIVE;
  protected $authorPHID;
  protected $primarySlug;
  protected $profileImagePHID;
  protected $icon;
  protected $color;
  protected $mailKey;

  protected $viewPolicy;
  protected $editPolicy;
  protected $joinPolicy;
  protected $isMembershipLocked;

  protected $parentProjectPHID;
  protected $hasWorkboard;
  protected $hasMilestones;
  protected $hasSubprojects;
  protected $milestoneNumber;

  protected $projectPath;
  protected $projectDepth;
  protected $projectPathKey;

  protected $properties = array();

  private $memberPHIDs = self::ATTACHABLE;
  private $watcherPHIDs = self::ATTACHABLE;
  private $sparseWatchers = self::ATTACHABLE;
  private $sparseMembers = self::ATTACHABLE;
  private $customFields = self::ATTACHABLE;
  private $profileImageFile = self::ATTACHABLE;
  private $slugs = self::ATTACHABLE;
  private $parentProject = self::ATTACHABLE;

  const TABLE_DATASOURCE_TOKEN = 'project_datasourcetoken';

  const ITEM_PICTURE = 'project.picture';
  const ITEM_PROFILE = 'project.profile';
  const ITEM_POINTS = 'project.points';
  const ITEM_WORKBOARD = 'project.workboard';
  const ITEM_MEMBERS = 'project.members';
  const ITEM_MANAGE = 'project.manage';
  const ITEM_MILESTONES = 'project.milestones';
  const ITEM_SUBPROJECTS = 'project.subprojects';

  public static function initializeNewProject(PhabricatorUser $actor) {
    $app = id(new PhabricatorApplicationQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withClasses(array('PhabricatorProjectApplication'))
      ->executeOne();

    $view_policy = $app->getPolicy(
      ProjectDefaultViewCapability::CAPABILITY);
    $edit_policy = $app->getPolicy(
      ProjectDefaultEditCapability::CAPABILITY);
    $join_policy = $app->getPolicy(
      ProjectDefaultJoinCapability::CAPABILITY);

    $default_icon = PhabricatorProjectIconSet::getDefaultIconKey();
    $default_color = PhabricatorProjectIconSet::getDefaultColorKey();

    return id(new PhabricatorProject())
      ->setAuthorPHID($actor->getPHID())
      ->setIcon($default_icon)
      ->setColor($default_color)
      ->setViewPolicy($view_policy)
      ->setEditPolicy($edit_policy)
      ->setJoinPolicy($join_policy)
      ->setIsMembershipLocked(0)
      ->attachMemberPHIDs(array())
      ->attachSlugs(array())
      ->setHasWorkboard(0)
      ->setHasMilestones(0)
      ->setHasSubprojects(0)
      ->attachParentProject(null);
  }

  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
      PhabricatorPolicyCapability::CAN_EDIT,
      PhabricatorPolicyCapability::CAN_JOIN,
    );
  }

  public function getPolicy($capability) {
    if ($this->isMilestone()) {
      return $this->getParentProject()->getPolicy($capability);
    }

    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        return $this->getViewPolicy();
      case PhabricatorPolicyCapability::CAN_EDIT:
        return $this->getEditPolicy();
      case PhabricatorPolicyCapability::CAN_JOIN:
        return $this->getJoinPolicy();
    }
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    if ($this->isMilestone()) {
      return $this->getParentProject()->hasAutomaticCapability(
        $capability,
        $viewer);
    }

    $can_edit = PhabricatorPolicyCapability::CAN_EDIT;

    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        if ($this->isUserMember($viewer->getPHID())) {
          // Project members can always view a project.
          return true;
        }
        break;
      case PhabricatorPolicyCapability::CAN_EDIT:
        $parent = $this->getParentProject();
        if ($parent) {
          $can_edit_parent = PhabricatorPolicyFilter::hasCapability(
            $viewer,
            $parent,
            $can_edit);
          if ($can_edit_parent) {
            return true;
          }
        }
        break;
      case PhabricatorPolicyCapability::CAN_JOIN:
        if (PhabricatorPolicyFilter::hasCapability($viewer, $this, $can_edit)) {
          // Project editors can always join a project.
          return true;
        }
        break;
    }

    return false;
  }

  public function describeAutomaticCapability($capability) {

    // TODO: Clarify the additional rules that parent and subprojects imply.

    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        return pht('Members of a project can always view it.');
      case PhabricatorPolicyCapability::CAN_JOIN:
        return pht('Users who can edit a project can always join it.');
    }
    return null;
  }

  public function getExtendedPolicy($capability, PhabricatorUser $viewer) {
    $extended = array();

    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        $parent = $this->getParentProject();
        if ($parent) {
          $extended[] = array(
            $parent,
            PhabricatorPolicyCapability::CAN_VIEW,
          );
        }
        break;
    }

    return $extended;
  }

  public function isUserMember($user_phid) {
    if ($this->memberPHIDs !== self::ATTACHABLE) {
      return in_array($user_phid, $this->memberPHIDs);
    }
    return $this->assertAttachedKey($this->sparseMembers, $user_phid);
  }

  public function setIsUserMember($user_phid, $is_member) {
    if ($this->sparseMembers === self::ATTACHABLE) {
      $this->sparseMembers = array();
    }
    $this->sparseMembers[$user_phid] = $is_member;
    return $this;
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'properties' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'name' => 'sort128',
        'status' => 'text32',
        'primarySlug' => 'text128?',
        'isMembershipLocked' => 'bool',
        'profileImagePHID' => 'phid?',
        'icon' => 'text32',
        'color' => 'text32',
        'mailKey' => 'bytes20',
        'joinPolicy' => 'policy',
        'parentProjectPHID' => 'phid?',
        'hasWorkboard' => 'bool',
        'hasMilestones' => 'bool',
        'hasSubprojects' => 'bool',
        'milestoneNumber' => 'uint32?',
        'projectPath' => 'hashpath64',
        'projectDepth' => 'uint32',
        'projectPathKey' => 'bytes4',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_icon' => array(
          'columns' => array('icon'),
        ),
        'key_color' => array(
          'columns' => array('color'),
        ),
        'key_milestone' => array(
          'columns' => array('parentProjectPHID', 'milestoneNumber'),
          'unique' => true,
        ),
        'key_primaryslug' => array(
          'columns' => array('primarySlug'),
          'unique' => true,
        ),
        'key_path' => array(
          'columns' => array('projectPath', 'projectDepth'),
        ),
        'key_pathkey' => array(
          'columns' => array('projectPathKey'),
          'unique' => true,
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorProjectProjectPHIDType::TYPECONST);
  }

  public function attachMemberPHIDs(array $phids) {
    $this->memberPHIDs = $phids;
    return $this;
  }

  public function getMemberPHIDs() {
    return $this->assertAttached($this->memberPHIDs);
  }

  public function isArchived() {
    return ($this->getStatus() == PhabricatorProjectStatus::STATUS_ARCHIVED);
  }

  public function getProfileImageURI() {
    return $this->getProfileImageFile()->getBestURI();
  }

  public function attachProfileImageFile(PhabricatorFile $file) {
    $this->profileImageFile = $file;
    return $this;
  }

  public function getProfileImageFile() {
    return $this->assertAttached($this->profileImageFile);
  }


  public function isUserWatcher($user_phid) {
    if ($this->watcherPHIDs !== self::ATTACHABLE) {
      return in_array($user_phid, $this->watcherPHIDs);
    }
    return $this->assertAttachedKey($this->sparseWatchers, $user_phid);
  }

  public function isUserAncestorWatcher($user_phid) {
    $is_watcher = $this->isUserWatcher($user_phid);

    if (!$is_watcher) {
      $parent = $this->getParentProject();
      if ($parent) {
        return $parent->isUserWatcher($user_phid);
      }
    }

    return $is_watcher;
  }

  public function getWatchedAncestorPHID($user_phid) {
    if ($this->isUserWatcher($user_phid)) {
      return $this->getPHID();
    }

    $parent = $this->getParentProject();
    if ($parent) {
      return $parent->getWatchedAncestorPHID($user_phid);
    }

    return null;
  }

  public function setIsUserWatcher($user_phid, $is_watcher) {
    if ($this->sparseWatchers === self::ATTACHABLE) {
      $this->sparseWatchers = array();
    }
    $this->sparseWatchers[$user_phid] = $is_watcher;
    return $this;
  }

  public function attachWatcherPHIDs(array $phids) {
    $this->watcherPHIDs = $phids;
    return $this;
  }

  public function getWatcherPHIDs() {
    return $this->assertAttached($this->watcherPHIDs);
  }

  public function getAllAncestorWatcherPHIDs() {
    $parent = $this->getParentProject();
    if ($parent) {
      $watchers = $parent->getAllAncestorWatcherPHIDs();
    } else {
      $watchers = array();
    }

    foreach ($this->getWatcherPHIDs() as $phid) {
      $watchers[$phid] = $phid;
    }

    return $watchers;
  }

  public function attachSlugs(array $slugs) {
    $this->slugs = $slugs;
    return $this;
  }

  public function getSlugs() {
    return $this->assertAttached($this->slugs);
  }

  public function getColor() {
    if ($this->isArchived()) {
      return PHUITagView::COLOR_DISABLED;
    }

    return $this->color;
  }

  public function getURI() {
    $id = $this->getID();
    return "/project/view/{$id}/";
  }

  public function getProfileURI() {
    $id = $this->getID();
    return "/project/profile/{$id}/";
  }

  public function save() {
    if (!$this->getMailKey()) {
      $this->setMailKey(Filesystem::readRandomCharacters(20));
    }

    if (!strlen($this->getPHID())) {
      $this->setPHID($this->generatePHID());
    }

    if (!strlen($this->getProjectPathKey())) {
      $hash = PhabricatorHash::digestForIndex($this->getPHID());
      $hash = substr($hash, 0, 4);
      $this->setProjectPathKey($hash);
    }

    $path = array();
    $depth = 0;
    if ($this->parentProjectPHID) {
      $parent = $this->getParentProject();
      $path[] = $parent->getProjectPath();
      $depth = $parent->getProjectDepth() + 1;
    }
    $path[] = $this->getProjectPathKey();
    $path = implode('', $path);

    $limit = self::getProjectDepthLimit();
    if ($depth >= $limit) {
      throw new Exception(pht('Project depth is too great.'));
    }

    $this->setProjectPath($path);
    $this->setProjectDepth($depth);

    $this->openTransaction();
      $result = parent::save();
      $this->updateDatasourceTokens();
    $this->saveTransaction();

    return $result;
  }

  public static function getProjectDepthLimit() {
    // This is limited by how many path hashes we can fit in the path
    // column.
    return 16;
  }

  public function updateDatasourceTokens() {
    $table = self::TABLE_DATASOURCE_TOKEN;
    $conn_w = $this->establishConnection('w');
    $id = $this->getID();

    $slugs = queryfx_all(
      $conn_w,
      'SELECT * FROM %T WHERE projectPHID = %s',
      id(new PhabricatorProjectSlug())->getTableName(),
      $this->getPHID());

    $all_strings = ipull($slugs, 'slug');
    $all_strings[] = $this->getDisplayName();
    $all_strings = implode(' ', $all_strings);

    $tokens = PhabricatorTypeaheadDatasource::tokenizeString($all_strings);

    $sql = array();
    foreach ($tokens as $token) {
      $sql[] = qsprintf($conn_w, '(%d, %s)', $id, $token);
    }

    $this->openTransaction();
      queryfx(
        $conn_w,
        'DELETE FROM %T WHERE projectID = %d',
        $table,
        $id);

      foreach (PhabricatorLiskDAO::chunkSQL($sql) as $chunk) {
        queryfx(
          $conn_w,
          'INSERT INTO %T (projectID, token) VALUES %Q',
          $table,
          $chunk);
      }
    $this->saveTransaction();
  }

  public function isMilestone() {
    return ($this->getMilestoneNumber() !== null);
  }

  public function getParentProject() {
    return $this->assertAttached($this->parentProject);
  }

  public function attachParentProject(PhabricatorProject $project = null) {
    $this->parentProject = $project;
    return $this;
  }

  public function getAncestorProjectPaths() {
    $parts = array();

    $path = $this->getProjectPath();
    $parent_length = (strlen($path) - 4);

    for ($ii = $parent_length; $ii > 0; $ii -= 4) {
      $parts[] = substr($path, 0, $ii);
    }

    return $parts;
  }

  public function getAncestorProjects() {
    $ancestors = array();

    $cursor = $this->getParentProject();
    while ($cursor) {
      $ancestors[] = $cursor;
      $cursor = $cursor->getParentProject();
    }

    return $ancestors;
  }

  public function supportsEditMembers() {
    if ($this->isMilestone()) {
      return false;
    }

    if ($this->getHasSubprojects()) {
      return false;
    }

    return true;
  }

  public function supportsMilestones() {
    if ($this->isMilestone()) {
      return false;
    }

    return true;
  }

  public function supportsSubprojects() {
    if ($this->isMilestone()) {
      return false;
    }

    return true;
  }

  public function loadNextMilestoneNumber() {
    $current = queryfx_one(
      $this->establishConnection('w'),
      'SELECT MAX(milestoneNumber) n
        FROM %T
        WHERE parentProjectPHID = %s',
      $this->getTableName(),
      $this->getPHID());

    if (!$current) {
      $number = 1;
    } else {
      $number = (int)$current['n'] + 1;
    }

    return $number;
  }

  public function getDisplayName() {
    $name = $this->getName();

    // If this is a milestone, show it as "Parent > Sprint 99".
    if ($this->isMilestone()) {
      $name = pht(
        '%s (%s)',
        $this->getParentProject()->getName(),
        $name);
    }

    return $name;
  }

  public function getDisplayIconKey() {
    if ($this->isMilestone()) {
      $key = PhabricatorProjectIconSet::getMilestoneIconKey();
    } else {
      $key = $this->getIcon();
    }

    return $key;
  }

  public function getDisplayIconIcon() {
    $key = $this->getDisplayIconKey();
    return PhabricatorProjectIconSet::getIconIcon($key);
  }

  public function getDisplayIconName() {
    $key = $this->getDisplayIconKey();
    return PhabricatorProjectIconSet::getIconName($key);
  }

  public function getDisplayColor() {
    if ($this->isMilestone()) {
      return $this->getParentProject()->getColor();
    }

    return $this->getColor();
  }

  public function getDisplayIconComposeIcon() {
    $icon = $this->getDisplayIconIcon();
    return $icon;
  }

  public function getDisplayIconComposeColor() {
    $color = $this->getDisplayColor();

    $map = array(
      'grey' => 'charcoal',
      'checkered' => 'backdrop',
    );

    return idx($map, $color, $color);
  }

  public function getProperty($key, $default = null) {
    return idx($this->properties, $key, $default);
  }

  public function setProperty($key, $value) {
    $this->properties[$key] = $value;
    return $this;
  }

  public function getDefaultWorkboardSort() {
    return $this->getProperty('workboard.sort.default');
  }

  public function setDefaultWorkboardSort($sort) {
    return $this->setProperty('workboard.sort.default', $sort);
  }

  public function getDefaultWorkboardFilter() {
    return $this->getProperty('workboard.filter.default');
  }

  public function setDefaultWorkboardFilter($filter) {
    return $this->setProperty('workboard.filter.default', $filter);
  }

  public function getWorkboardBackgroundColor() {
    return $this->getProperty('workboard.background');
  }

  public function setWorkboardBackgroundColor($color) {
    return $this->setProperty('workboard.background', $color);
  }

  public function getDisplayWorkboardBackgroundColor() {
    $color = $this->getWorkboardBackgroundColor();

    if ($color === null) {
      $parent = $this->getParentProject();
      if ($parent) {
        return $parent->getDisplayWorkboardBackgroundColor();
      }
    }

    if ($color === 'none') {
      $color = null;
    }

    return $color;
  }


/* -(  PhabricatorCustomFieldInterface  )------------------------------------ */


  public function getCustomFieldSpecificationForRole($role) {
    return PhabricatorEnv::getEnvConfig('projects.fields');
  }

  public function getCustomFieldBaseClass() {
    return 'PhabricatorProjectCustomField';
  }

  public function getCustomFields() {
    return $this->assertAttached($this->customFields);
  }

  public function attachCustomFields(PhabricatorCustomFieldAttachment $fields) {
    $this->customFields = $fields;
    return $this;
  }


/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */


  public function getApplicationTransactionEditor() {
    return new PhabricatorProjectTransactionEditor();
  }

  public function getApplicationTransactionObject() {
    return $this;
  }

  public function getApplicationTransactionTemplate() {
    return new PhabricatorProjectTransaction();
  }

  public function willRenderTimeline(
    PhabricatorApplicationTransactionView $timeline,
    AphrontRequest $request) {

    return $timeline;
  }


/* -(  PhabricatorDestructibleInterface  )----------------------------------- */


  public function destroyObjectPermanently(
    PhabricatorDestructionEngine $engine) {

    $this->openTransaction();
      $this->delete();

      $columns = id(new PhabricatorProjectColumn())
        ->loadAllWhere('projectPHID = %s', $this->getPHID());
      foreach ($columns as $column) {
        $engine->destroyObject($column);
      }

      $slugs = id(new PhabricatorProjectSlug())
        ->loadAllWhere('projectPHID = %s', $this->getPHID());
      foreach ($slugs as $slug) {
        $slug->delete();
      }

    $this->saveTransaction();
  }


/* -(  PhabricatorFulltextInterface  )--------------------------------------- */


  public function newFulltextEngine() {
    return new PhabricatorProjectFulltextEngine();
  }


/* -(  PhabricatorFerretInterface  )--------------------------------------- */


  public function newFerretEngine() {
    return new PhabricatorProjectFerretEngine();
  }


/* -(  PhabricatorConduitResultInterface  )---------------------------------- */


  public function getFieldSpecificationsForConduit() {
    return array(
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('name')
        ->setType('string')
        ->setDescription(pht('The name of the project.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('slug')
        ->setType('string')
        ->setDescription(pht('Primary slug/hashtag.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('milestone')
        ->setType('int?')
        ->setDescription(pht('For milestones, milestone sequence number.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('parent')
        ->setType('map<string, wild>?')
        ->setDescription(
          pht(
            'For subprojects and milestones, a brief description of the '.
            'parent project.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('depth')
        ->setType('int')
        ->setDescription(
          pht(
            'For subprojects and milestones, depth of this project in the '.
            'tree. Root projects have depth 0.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('icon')
        ->setType('map<string, wild>')
        ->setDescription(pht('Information about the project icon.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('color')
        ->setType('map<string, wild>')
        ->setDescription(pht('Information about the project color.')),
    );
  }

  public function getFieldValuesForConduit() {
    $color_key = $this->getColor();
    $color_name = PhabricatorProjectIconSet::getColorName($color_key);

    if ($this->isMilestone()) {
      $milestone = (int)$this->getMilestoneNumber();
    } else {
      $milestone = null;
    }

    $parent = $this->getParentProject();
    if ($parent) {
      $parent_ref = $parent->getRefForConduit();
    } else {
      $parent_ref = null;
    }

    return array(
      'name' => $this->getName(),
      'slug' => $this->getPrimarySlug(),
      'milestone' => $milestone,
      'depth' => (int)$this->getProjectDepth(),
      'parent' => $parent_ref,
      'icon' => array(
        'key' => $this->getDisplayIconKey(),
        'name' => $this->getDisplayIconName(),
        'icon' => $this->getDisplayIconIcon(),
      ),
      'color' => array(
        'key' => $color_key,
        'name' => $color_name,
      ),
    );
  }

  public function getConduitSearchAttachments() {
    return array(
      id(new PhabricatorProjectsMembersSearchEngineAttachment())
        ->setAttachmentKey('members'),
      id(new PhabricatorProjectsWatchersSearchEngineAttachment())
        ->setAttachmentKey('watchers'),
      id(new PhabricatorProjectsAncestorsSearchEngineAttachment())
        ->setAttachmentKey('ancestors'),
    );
  }

  /**
   * Get an abbreviated representation of this project for use in providing
   * "parent" and "ancestor" information.
   */
  public function getRefForConduit() {
    return array(
      'id' => (int)$this->getID(),
      'phid' => $this->getPHID(),
      'name' => $this->getName(),
    );
  }


/* -(  PhabricatorColumnProxyInterface  )------------------------------------ */


  public function getProxyColumnName() {
    return $this->getName();
  }

  public function getProxyColumnIcon() {
    return $this->getDisplayIconIcon();
  }

  public function getProxyColumnClass() {
    if ($this->isMilestone()) {
      return 'phui-workboard-column-milestone';
    }

    return null;
  }


}
