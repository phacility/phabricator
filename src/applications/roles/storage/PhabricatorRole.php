<?php

final class PhabricatorRole extends PhabricatorRoleDAO
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
    PhabricatorRoleColumnProxyInterface,
    PhabricatorSpacesInterface,
    PhabricatorEditEngineSubtypeInterface,
    PhabricatorRoleWorkboardInterface {

  protected $name;
  protected $status = PhabricatorRoleStatus::STATUS_ACTIVE;
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

  protected $parentRolePHID;
  protected $hasWorkboard;
  protected $hasMilestones;
  protected $hasSubroles;
  protected $milestoneNumber;

  protected $rolePath;
  protected $roleDepth;
  protected $rolePathKey;

  protected $properties = array();
  protected $spacePHID;
  protected $subtype;

  private $memberPHIDs = self::ATTACHABLE;
  private $watcherPHIDs = self::ATTACHABLE;
  private $sparseWatchers = self::ATTACHABLE;
  private $sparseMembers = self::ATTACHABLE;
  private $customFields = self::ATTACHABLE;
  private $profileImageFile = self::ATTACHABLE;
  private $slugs = self::ATTACHABLE;
  private $parentRole = self::ATTACHABLE;

  const TABLE_DATASOURCE_TOKEN = 'role_datasourcetoken';

  const ITEM_PICTURE = 'role.picture';
  const ITEM_PROFILE = 'role.profile';
  const ITEM_POINTS = 'role.points';
  const ITEM_WORKBOARD = 'role.workboard';
  const ITEM_REPORTS = 'role.reports';
  const ITEM_MEMBERS = 'role.members';
  const ITEM_MANAGE = 'role.manage';
  const ITEM_MILESTONES = 'role.milestones';
  const ITEM_SUBROLES = 'role.subroles';

  public static function initializeNewRole(
    PhabricatorUser $actor,
    PhabricatorRole $parent = null) {

    $app = id(new PhabricatorApplicationQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withClasses(array('PhabricatorRoleApplication'))
      ->executeOne();

    $view_policy = $app->getPolicy(
      RoleDefaultViewCapability::CAPABILITY);
    $edit_policy = $app->getPolicy(
      RoleDefaultEditCapability::CAPABILITY);
    $join_policy = $app->getPolicy(
      RoleDefaultJoinCapability::CAPABILITY);

    // If this is the child of some other role, default the Space to the
    // Space of the parent.
    if ($parent) {
      $space_phid = $parent->getSpacePHID();
    } else {
      $space_phid = $actor->getDefaultSpacePHID();
    }

    $default_icon = PhabricatorRoleIconSet::getDefaultIconKey();
    $default_color = PhabricatorRoleIconSet::getDefaultColorKey();

    return id(new PhabricatorRole())
      ->setAuthorPHID($actor->getPHID())
      ->setIcon($default_icon)
      ->setColor($default_color)
      ->setViewPolicy($view_policy)
      ->setEditPolicy($edit_policy)
      ->setJoinPolicy($join_policy)
      ->setSpacePHID($space_phid)
      ->setIsMembershipLocked(0)
      ->attachMemberPHIDs(array())
      ->attachSlugs(array())
      ->setHasWorkboard(0)
      ->setHasMilestones(0)
      ->setHasSubroles(0)
      ->setSubtype(PhabricatorEditEngineSubtype::SUBTYPE_DEFAULT)
      ->attachParentRole($parent);
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
      return $this->getParentRole()->getPolicy($capability);
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
      return $this->getParentRole()->hasAutomaticCapability(
        $capability,
        $viewer);
    }

    $can_edit = PhabricatorPolicyCapability::CAN_EDIT;

    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        if ($this->isUserMember($viewer->getPHID())) {
          // Role members can always view a role.
          return true;
        }
        break;
      case PhabricatorPolicyCapability::CAN_EDIT:
        $parent = $this->getParentRole();
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
          // Role editors can always join a role.
          return true;
        }
        break;
    }

    return false;
  }

  public function describeAutomaticCapability($capability) {

    // TODO: Clarify the additional rules that parent and subroles imply.

    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        return pht('Members of a role can always view it.');
      case PhabricatorPolicyCapability::CAN_JOIN:
        return pht('Users who can edit a role can always join it.');
    }
    return null;
  }

  public function getExtendedPolicy($capability, PhabricatorUser $viewer) {
    $extended = array();

    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        $parent = $this->getParentRole();
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
        'parentRolePHID' => 'phid?',
        'hasWorkboard' => 'bool',
        'hasMilestones' => 'bool',
        'hasSubroles' => 'bool',
        'milestoneNumber' => 'uint32?',
        'rolePath' => 'hashpath64',
        'roleDepth' => 'uint32',
        'rolePathKey' => 'bytes4',
        'subtype' => 'text64',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_icon' => array(
          'columns' => array('icon'),
        ),
        'key_color' => array(
          'columns' => array('color'),
        ),
        'key_milestone' => array(
          'columns' => array('parentRolePHID', 'milestoneNumber'),
          'unique' => true,
        ),
        'key_primaryslug' => array(
          'columns' => array('primarySlug'),
          'unique' => true,
        ),
        'key_path' => array(
          'columns' => array('rolePath', 'roleDepth'),
        ),
        'key_pathkey' => array(
          'columns' => array('rolePathKey'),
          'unique' => true,
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorRoleRolePHIDType::TYPECONST);
  }

  public function attachMemberPHIDs(array $phids) {
    $this->memberPHIDs = $phids;
    return $this;
  }

  public function getMemberPHIDs() {
    return $this->assertAttached($this->memberPHIDs);
  }

  public function isArchived() {
    return ($this->getStatus() == PhabricatorRoleStatus::STATUS_ARCHIVED);
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
      $parent = $this->getParentRole();
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

    $parent = $this->getParentRole();
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
    $parent = $this->getParentRole();
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
    return "/role/view/{$id}/";
  }

  public function getProfileURI() {
    $id = $this->getID();
    return "/role/profile/{$id}/";
  }

  public function getWorkboardURI() {
    return urisprintf('/role/board/%d/', $this->getID());
  }

  public function getReportsURI() {
    return urisprintf('/role/reports/%d/', $this->getID());
  }

  public function save() {
    if (!$this->getMailKey()) {
      $this->setMailKey(Filesystem::readRandomCharacters(20));
    }

    if ($this->getPHID() === null || $this->getPHID() === '') {
      $this->setPHID($this->generatePHID());
    }

    if ($this->getRolePathKey() === null || $this->getRolePathKey() === '') {
      $hash = PhabricatorHash::digestForIndex($this->getPHID());
      $hash = substr($hash, 0, 4);
      $this->setRolePathKey($hash);
    }

    $path = array();
    $depth = 0;
    if ($this->parentRolePHID) {
      $parent = $this->getParentRole();
      $path[] = $parent->getRolePath();
      $depth = $parent->getRoleDepth() + 1;
    }
    $path[] = $this->getRolePathKey();
    $path = implode('', $path);

    $limit = self::getRoleDepthLimit();
    if ($depth >= $limit) {
      throw new Exception(pht('Role depth is too great.'));
    }

    $this->setRolePath($path);
    $this->setRoleDepth($depth);

    $this->openTransaction();
      $result = parent::save();
      $this->updateDatasourceTokens();
    $this->saveTransaction();

    return $result;
  }

  public static function getRoleDepthLimit() {
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
      'SELECT * FROM %T WHERE rolePHID = %s',
      id(new PhabricatorRoleSlug())->getTableName(),
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
        'DELETE FROM %T WHERE roleID = %d',
        $table,
        $id);

      foreach (PhabricatorLiskDAO::chunkSQL($sql) as $chunk) {
        queryfx(
          $conn_w,
          'INSERT INTO %T (roleID, token) VALUES %LQ',
          $table,
          $chunk);
      }
    $this->saveTransaction();
  }

  public function isMilestone() {
    return ($this->getMilestoneNumber() !== null);
  }

  public function getParentRole() {
    return $this->assertAttached($this->parentRole);
  }

  public function attachParentRole(PhabricatorRole $role = null) {
    $this->parentRole = $role;
    return $this;
  }

  public function getAncestorRolePaths() {
    $parts = array();

    $path = $this->getRolePath();
    $parent_length = (strlen($path) - 4);

    for ($ii = $parent_length; $ii > 0; $ii -= 4) {
      $parts[] = substr($path, 0, $ii);
    }

    return $parts;
  }

  public function getAncestorRoles() {
    $ancestors = array();

    $cursor = $this->getParentRole();
    while ($cursor) {
      $ancestors[] = $cursor;
      $cursor = $cursor->getParentRole();
    }

    return $ancestors;
  }

  public function supportsEditMembers() {
    if ($this->isMilestone()) {
      return false;
    }

    if ($this->getHasSubroles()) {
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

  public function supportsSubroles() {
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
        WHERE parentRolePHID = %s',
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
        $this->getParentRole()->getName(),
        $name);
    }

    return $name;
  }

  public function getDisplayIconKey() {
    if ($this->isMilestone()) {
      $key = PhabricatorRoleIconSet::getMilestoneIconKey();
    } else {
      $key = $this->getIcon();
    }

    return $key;
  }

  public function getDisplayIconIcon() {
    $key = $this->getDisplayIconKey();
    return PhabricatorRoleIconSet::getIconIcon($key);
  }

  public function getDisplayIconName() {
    $key = $this->getDisplayIconKey();
    return PhabricatorRoleIconSet::getIconName($key);
  }

  public function getDisplayColor() {
    if ($this->isMilestone()) {
      return $this->getParentRole()->getColor();
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
      $parent = $this->getParentRole();
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
    return PhabricatorEnv::getEnvConfig('roles.fields');
  }

  public function getCustomFieldBaseClass() {
    return 'PhabricatorRoleCustomField';
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
    return new PhabricatorRoleTransactionEditor();
  }

  public function getApplicationTransactionTemplate() {
    return new PhabricatorRoleTransaction();
  }


/* -(  PhabricatorSpacesInterface  )----------------------------------------- */


  public function getSpacePHID() {
    if ($this->isMilestone()) {
      return $this->getParentRole()->getSpacePHID();
    }
    return $this->spacePHID;
  }


/* -(  PhabricatorDestructibleInterface  )----------------------------------- */


  public function destroyObjectPermanently(
    PhabricatorDestructionEngine $engine) {

    $this->openTransaction();
      $this->delete();

      $columns = id(new PhabricatorRoleColumn())
        ->loadAllWhere('rolePHID = %s', $this->getPHID());
      foreach ($columns as $column) {
        $engine->destroyObject($column);
      }

      $slugs = id(new PhabricatorRoleSlug())
        ->loadAllWhere('rolePHID = %s', $this->getPHID());
      foreach ($slugs as $slug) {
        $slug->delete();
      }

    $this->saveTransaction();
  }


/* -(  PhabricatorFulltextInterface  )--------------------------------------- */


  public function newFulltextEngine() {
    return new PhabricatorRoleFulltextEngine();
  }


/* -(  PhabricatorFerretInterface  )--------------------------------------- */


  public function newFerretEngine() {
    return new PhabricatorRoleFerretEngine();
  }


/* -(  PhabricatorConduitResultInterface  )---------------------------------- */


  public function getFieldSpecificationsForConduit() {
    return array(
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('name')
        ->setType('string')
        ->setDescription(pht('The name of the role.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('slug')
        ->setType('string')
        ->setDescription(pht('Primary slug/hashtag.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('subtype')
        ->setType('string')
        ->setDescription(pht('Subtype of the role.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('milestone')
        ->setType('int?')
        ->setDescription(pht('For milestones, milestone sequence number.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('parent')
        ->setType('map<string, wild>?')
        ->setDescription(
          pht(
            'For subroles and milestones, a brief description of the '.
            'parent role.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('depth')
        ->setType('int')
        ->setDescription(
          pht(
            'For subroles and milestones, depth of this role in the '.
            'tree. Root roles have depth 0.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('icon')
        ->setType('map<string, wild>')
        ->setDescription(pht('Information about the role icon.')),
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('color')
        ->setType('map<string, wild>')
        ->setDescription(pht('Information about the role color.')),
    );
  }

  public function getFieldValuesForConduit() {
    $color_key = $this->getColor();
    $color_name = PhabricatorRoleIconSet::getColorName($color_key);

    if ($this->isMilestone()) {
      $milestone = (int)$this->getMilestoneNumber();
    } else {
      $milestone = null;
    }

    $parent = $this->getParentRole();
    if ($parent) {
      $parent_ref = $parent->getRefForConduit();
    } else {
      $parent_ref = null;
    }

    return array(
      'name' => $this->getName(),
      'slug' => $this->getPrimarySlug(),
      'subtype' => $this->getSubtype(),
      'milestone' => $milestone,
      'depth' => (int)$this->getRoleDepth(),
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
      id(new PhabricatorRolesMembersSearchEngineAttachment())
        ->setAttachmentKey('members'),
      id(new PhabricatorRolesWatchersSearchEngineAttachment())
        ->setAttachmentKey('watchers'),
      id(new PhabricatorRolesAncestorsSearchEngineAttachment())
        ->setAttachmentKey('ancestors'),
    );
  }

  /**
   * Get an abbreviated representation of this role for use in providing
   * "parent" and "ancestor" information.
   */
  public function getRefForConduit() {
    return array(
      'id' => (int)$this->getID(),
      'phid' => $this->getPHID(),
      'name' => $this->getName(),
    );
  }


/* -(  PhabricatorRoleColumnProxyInterface  )------------------------------------ */


  public function getProxyRoleColumnName() {
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


/* -(  PhabricatorEditEngineSubtypeInterface  )------------------------------ */


  public function getEditEngineSubtype() {
    return $this->getSubtype();
  }

  public function setEditEngineSubtype($value) {
    return $this->setSubtype($value);
  }

  public function newEditEngineSubtypeMap() {
    $config = PhabricatorEnv::getEnvConfig('roles.subtypes');
    return PhabricatorEditEngineSubtype::newSubtypeMap($config)
      ->setDatasource(new PhabricatorRoleSubtypeDatasource());
  }

  public function newSubtypeObject() {
    $subtype_key = $this->getEditEngineSubtype();
    $subtype_map = $this->newEditEngineSubtypeMap();
    return $subtype_map->getSubtype($subtype_key);
  }

}
