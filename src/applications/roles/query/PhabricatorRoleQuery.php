<?php

final class PhabricatorRoleQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $memberPHIDs;
  private $watcherPHIDs;
  private $slugs;
  private $slugNormals;
  private $slugMap;
  private $allSlugs;
  private $names;
  private $namePrefixes;
  private $nameTokens;
  private $icons;
  private $colors;
  private $ancestorPHIDs;
  private $parentPHIDs;
  private $isMilestone;
  private $hasSubroles;
  private $minDepth;
  private $maxDepth;
  private $minMilestoneNumber;
  private $maxMilestoneNumber;
  private $subtypes;

  private $status       = 'status-any';
  const STATUS_ANY      = 'status-any';
  const STATUS_OPEN     = 'status-open';
  const STATUS_CLOSED   = 'status-closed';
  const STATUS_ACTIVE   = 'status-active';
  const STATUS_ARCHIVED = 'status-archived';
  private $statuses;

  private $needSlugs;
  private $needMembers;
  private $needAncestorMembers;
  private $needWatchers;
  private $needImages;

  public function withIDs(array $ids) {
    $this->ids = $ids;
    return $this;
  }

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withStatus($status) {
    $this->status = $status;
    return $this;
  }

  public function withStatuses(array $statuses) {
    $this->statuses = $statuses;
    return $this;
  }

  public function withMemberPHIDs(array $member_phids) {
    $this->memberPHIDs = $member_phids;
    return $this;
  }

  public function withWatcherPHIDs(array $watcher_phids) {
    $this->watcherPHIDs = $watcher_phids;
    return $this;
  }

  public function withSlugs(array $slugs) {
    $this->slugs = $slugs;
    return $this;
  }

  public function withNames(array $names) {
    $this->names = $names;
    return $this;
  }

  public function withNamePrefixes(array $prefixes) {
    $this->namePrefixes = $prefixes;
    return $this;
  }

  public function withNameTokens(array $tokens) {
    $this->nameTokens = array_values($tokens);
    return $this;
  }

  public function withIcons(array $icons) {
    $this->icons = $icons;
    return $this;
  }

  public function withColors(array $colors) {
    $this->colors = $colors;
    return $this;
  }

  public function withParentRolePHIDs($parent_phids) {
    $this->parentPHIDs = $parent_phids;
    return $this;
  }

  public function withAncestorRolePHIDs($ancestor_phids) {
    $this->ancestorPHIDs = $ancestor_phids;
    return $this;
  }

  public function withIsMilestone($is_milestone) {
    $this->isMilestone = $is_milestone;
    return $this;
  }

  public function withHasSubroles($has_subroles) {
    $this->hasSubroles = $has_subroles;
    return $this;
  }

  public function withDepthBetween($min, $max) {
    $this->minDepth = $min;
    $this->maxDepth = $max;
    return $this;
  }

  public function withMilestoneNumberBetween($min, $max) {
    $this->minMilestoneNumber = $min;
    $this->maxMilestoneNumber = $max;
    return $this;
  }

  public function withSubtypes(array $subtypes) {
    $this->subtypes = $subtypes;
    return $this;
  }

  public function needMembers($need_members) {
    $this->needMembers = $need_members;
    return $this;
  }

  public function needAncestorMembers($need_ancestor_members) {
    $this->needAncestorMembers = $need_ancestor_members;
    return $this;
  }

  public function needWatchers($need_watchers) {
    $this->needWatchers = $need_watchers;
    return $this;
  }

  public function needImages($need_images) {
    $this->needImages = $need_images;
    return $this;
  }

  public function needSlugs($need_slugs) {
    $this->needSlugs = $need_slugs;
    return $this;
  }

  public function newResultObject() {
    return new PhabricatorRole();
  }

  protected function getDefaultOrderVector() {
    return array('name');
  }

  public function getBuiltinOrders() {
    return array(
      'name' => array(
        'vector' => array('name'),
        'name' => pht('Name'),
      ),
    ) + parent::getBuiltinOrders();
  }

  public function getOrderableColumns() {
    return parent::getOrderableColumns() + array(
      'name' => array(
        'table' => $this->getPrimaryTableAlias(),
        'column' => 'name',
        'reverse' => true,
        'type' => 'string',
        'unique' => true,
      ),
      'milestoneNumber' => array(
        'table' => $this->getPrimaryTableAlias(),
        'column' => 'milestoneNumber',
        'type' => 'int',
      ),
      'status' => array(
        'table' => $this->getPrimaryTableAlias(),
        'column' => 'status',
        'type' => 'int',
      ),
    );
  }

  protected function newPagingMapFromPartialObject($object) {
    return array(
      'id' => (int)$object->getID(),
      'name' => $object->getName(),
      'status' => $object->getStatus(),
    );
  }

  public function getSlugMap() {
    if ($this->slugMap === null) {
      throw new PhutilInvalidStateException('execute');
    }
    return $this->slugMap;
  }

  protected function willExecute() {
    $this->slugMap = array();
    $this->slugNormals = array();
    $this->allSlugs = array();
    if ($this->slugs) {
      foreach ($this->slugs as $slug) {
        if (PhabricatorSlug::isValidProjectSlug($slug)) {
          $normal = PhabricatorSlug::normalizeProjectSlug($slug);
          $this->slugNormals[$slug] = $normal;
          $this->allSlugs[$normal] = $normal;
        }

        // NOTE: At least for now, we query for the normalized slugs but also
        // for the slugs exactly as entered. This allows older roles with
        // slugs that are no longer valid to continue to work.
        $this->allSlugs[$slug] = $slug;
     }
    }
  }

  protected function loadPage() {
    return $this->loadStandardPage($this->newResultObject());
  }

  protected function willFilterPage(array $roles) {
    $ancestor_paths = array();
    foreach ($roles as $role) {
      foreach ($role->getAncestorRolePaths() as $path) {
        $ancestor_paths[$path] = $path;
      }
    }

    if ($ancestor_paths) {
      $ancestors = id(new PhabricatorRole())->loadAllWhere(
        'rolePath IN (%Ls)',
        $ancestor_paths);
    } else {
      $ancestors = array();
    }

    $roles = $this->linkRoleGraph($roles, $ancestors);

    $viewer_phid = $this->getViewer()->getPHID();

    $material_type = PhabricatorRoleMaterializedMemberEdgeType::EDGECONST;
    $watcher_type = PhabricatorObjectHasWatcherEdgeType::EDGECONST;

    $types = array();
    $types[] = $material_type;
    if ($this->needWatchers) {
      $types[] = $watcher_type;
    }

    $all_graph = $this->getAllReachableAncestors($roles);

    // See T13484. If the graph is damaged (and contains a cycle or an edge
    // pointing at a role which has been destroyed), some of the nodes we
    // started with may be filtered out by reachability tests. If any of the
    // roles we are linking up don't have available ancestors, filter them
    // out.

    foreach ($roles as $key => $role) {
      $role_phid = $role->getPHID();
      if (!isset($all_graph[$role_phid])) {
        $this->didRejectResult($role);
        unset($roles[$key]);
        continue;
      }
    }

    if (!$roles) {
      return array();
    }

    // NOTE: Although we may not need much information about ancestors, we
    // always need to test if the viewer is a member, because we will return
    // ancestor roles to the policy filter via ExtendedPolicy calls. If
    // we skip populating membership data on a parent, the policy framework
    // will think the user is not a member of the parent role.

    $all_sources = array();
    foreach ($all_graph as $role) {
      // For milestones, we need parent members.
      if ($role->isMilestone()) {
        $parent_phid = $role->getParentRolePHID();
        $all_sources[$parent_phid] = $parent_phid;
      }

      $phid = $role->getPHID();
      $all_sources[$phid] = $phid;
    }

    $edge_query = id(new PhabricatorEdgeQuery())
      ->withSourcePHIDs($all_sources)
      ->withEdgeTypes($types);

    $need_all_edges =
      $this->needMembers ||
      $this->needWatchers ||
      $this->needAncestorMembers;

    // If we only need to know if the viewer is a member, we can restrict
    // the query to just their PHID.
    $any_edges = true;
    if (!$need_all_edges) {
      if ($viewer_phid) {
        $edge_query->withDestinationPHIDs(array($viewer_phid));
      } else {
        // If we don't need members or watchers and don't have a viewer PHID
        // (viewer is logged-out or omnipotent), they'll never be a member
        // so we don't need to issue this query at all.
        $any_edges = false;
      }
    }

    if ($any_edges) {
      $edge_query->execute();
    }

    $membership_roles = array();
    foreach ($all_graph as $role) {
      $role_phid = $role->getPHID();

      if ($role->isMilestone()) {
        $source_phids = array($role->getParentRolePHID());
      } else {
        $source_phids = array($role_phid);
      }

      if ($any_edges) {
        $member_phids = $edge_query->getDestinationPHIDs(
          $source_phids,
          array($material_type));
      } else {
        $member_phids = array();
      }

      if (in_array($viewer_phid, $member_phids)) {
        $membership_roles[$role_phid] = $role;
      }

      if ($this->needMembers || $this->needAncestorMembers) {
        $role->attachMemberPHIDs($member_phids);
      }

      if ($this->needWatchers) {
        $watcher_phids = $edge_query->getDestinationPHIDs(
          array($role_phid),
          array($watcher_type));
        $role->attachWatcherPHIDs($watcher_phids);
        $role->setIsUserWatcher(
          $viewer_phid,
          in_array($viewer_phid, $watcher_phids));
      }
    }

    // If we loaded ancestor members, we've already populated membership
    // lists above, so we can skip this step.
    if (!$this->needAncestorMembers) {
      $member_graph = $this->getAllReachableAncestors($membership_roles);

      foreach ($all_graph as $phid => $role) {
        $is_member = isset($member_graph[$phid]);
        $role->setIsUserMember($viewer_phid, $is_member);
      }
    }

    return $roles;
  }

  protected function didFilterPage(array $roles) {
    $viewer = $this->getViewer();

    if ($this->needImages) {
      $need_images = $roles;

      // First, try to load custom profile images for any roles with custom
      // images.
      $file_phids = array();
      foreach ($need_images as $key => $role) {
        $image_phid = $role->getProfileImagePHID();
        if ($image_phid) {
          $file_phids[$key] = $image_phid;
        }
      }

      if ($file_phids) {
        $files = id(new PhabricatorFileQuery())
          ->setParentQuery($this)
          ->setViewer($viewer)
          ->withPHIDs($file_phids)
          ->execute();
        $files = mpull($files, null, 'getPHID');

        foreach ($file_phids as $key => $image_phid) {
          $file = idx($files, $image_phid);
          if (!$file) {
            continue;
          }

          $need_images[$key]->attachProfileImageFile($file);
          unset($need_images[$key]);
        }
      }

      // For roles with default images, or roles where the custom image
      // failed to load, load a builtin image.
      if ($need_images) {
        $builtin_map = array();
        $builtins = array();
        foreach ($need_images as $key => $role) {
          $icon = $role->getIcon();

          $builtin_name = PhabricatorRoleIconSet::getIconImage($icon);
          $builtin_name = 'roles/'.$builtin_name;

          $builtin = id(new PhabricatorFilesOnDiskBuiltinFile())
            ->setName($builtin_name);

          $builtin_key = $builtin->getBuiltinFileKey();

          $builtins[] = $builtin;
          $builtin_map[$key] = $builtin_key;
        }

        $builtin_files = PhabricatorFile::loadBuiltins(
          $viewer,
          $builtins);

        foreach ($need_images as $key => $role) {
          $builtin_key = $builtin_map[$key];
          $builtin_file = $builtin_files[$builtin_key];
          $role->attachProfileImageFile($builtin_file);
        }
      }
    }

    $this->loadSlugs($roles);

    return $roles;
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    if ($this->status != self::STATUS_ANY) {
      switch ($this->status) {
        case self::STATUS_OPEN:
        case self::STATUS_ACTIVE:
          $filter = array(
            PhabricatorRoleStatus::STATUS_ACTIVE,
          );
          break;
        case self::STATUS_CLOSED:
        case self::STATUS_ARCHIVED:
          $filter = array(
            PhabricatorRoleStatus::STATUS_ARCHIVED,
          );
          break;
        default:
          throw new Exception(
            pht(
              "Unknown role status '%s'!",
              $this->status));
      }
      $where[] = qsprintf(
        $conn,
        'role.status IN (%Ld)',
        $filter);
    }

    if ($this->statuses !== null) {
      $where[] = qsprintf(
        $conn,
        'role.status IN (%Ls)',
        $this->statuses);
    }

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'role.id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn,
        'role.phid IN (%Ls)',
        $this->phids);
    }

    if ($this->memberPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'e.dst IN (%Ls)',
        $this->memberPHIDs);
    }

    if ($this->watcherPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'w.dst IN (%Ls)',
        $this->watcherPHIDs);
    }

    if ($this->slugs !== null) {
      $where[] = qsprintf(
        $conn,
        'slug.slug IN (%Ls)',
        $this->allSlugs);
    }

    if ($this->names !== null) {
      $where[] = qsprintf(
        $conn,
        'role.name IN (%Ls)',
        $this->names);
    }

    if ($this->namePrefixes) {
      $parts = array();
      foreach ($this->namePrefixes as $name_prefix) {
        $parts[] = qsprintf(
          $conn,
          'role.name LIKE %>',
          $name_prefix);
      }
      $where[] = qsprintf($conn, '%LO', $parts);
    }

    if ($this->icons !== null) {
      $where[] = qsprintf(
        $conn,
        'role.icon IN (%Ls)',
        $this->icons);
    }

    if ($this->colors !== null) {
      $where[] = qsprintf(
        $conn,
        'role.color IN (%Ls)',
        $this->colors);
    }

    if ($this->parentPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'role.parentRolePHID IN (%Ls)',
        $this->parentPHIDs);
    }

    if ($this->ancestorPHIDs !== null) {
      $ancestor_paths = queryfx_all(
        $conn,
        'SELECT rolePath, roleDepth FROM %T WHERE phid IN (%Ls)',
        id(new PhabricatorRole())->getTableName(),
        $this->ancestorPHIDs);
      if (!$ancestor_paths) {
        throw new PhabricatorEmptyQueryException();
      }

      $sql = array();
      foreach ($ancestor_paths as $ancestor_path) {
        $sql[] = qsprintf(
          $conn,
          '(role.rolePath LIKE %> AND role.roleDepth > %d)',
          $ancestor_path['rolePath'],
          $ancestor_path['roleDepth']);
      }

      $where[] = qsprintf($conn, '%LO', $sql);

      $where[] = qsprintf(
        $conn,
        'role.parentRolePHID IS NOT NULL');
    }

    if ($this->isMilestone !== null) {
      if ($this->isMilestone) {
        $where[] = qsprintf(
          $conn,
          'role.milestoneNumber IS NOT NULL');
      } else {
        $where[] = qsprintf(
          $conn,
          'role.milestoneNumber IS NULL');
      }
    }


    if ($this->hasSubroles !== null) {
      $where[] = qsprintf(
        $conn,
        'role.hasSubroles = %d',
        (int)$this->hasSubroles);
    }

    if ($this->minDepth !== null) {
      $where[] = qsprintf(
        $conn,
        'role.roleDepth >= %d',
        $this->minDepth);
    }

    if ($this->maxDepth !== null) {
      $where[] = qsprintf(
        $conn,
        'role.roleDepth <= %d',
        $this->maxDepth);
    }

    if ($this->minMilestoneNumber !== null) {
      $where[] = qsprintf(
        $conn,
        'role.milestoneNumber >= %d',
        $this->minMilestoneNumber);
    }

    if ($this->maxMilestoneNumber !== null) {
      $where[] = qsprintf(
        $conn,
        'role.milestoneNumber <= %d',
        $this->maxMilestoneNumber);
    }

    if ($this->subtypes !== null) {
      $where[] = qsprintf(
        $conn,
        'role.subtype IN (%Ls)',
        $this->subtypes);
    }

    return $where;
  }

  protected function shouldGroupQueryResultRows() {
    if ($this->memberPHIDs || $this->watcherPHIDs || $this->nameTokens) {
      return true;
    }

    if ($this->slugs) {
      return true;
    }

    return parent::shouldGroupQueryResultRows();
  }

  protected function buildJoinClauseParts(AphrontDatabaseConnection $conn) {
    $joins = parent::buildJoinClauseParts($conn);

    if ($this->memberPHIDs !== null) {
      $joins[] = qsprintf(
        $conn,
        'JOIN %T e ON e.src = role.phid AND e.type = %d',
        PhabricatorEdgeConfig::TABLE_NAME_EDGE,
        PhabricatorRoleMaterializedMemberEdgeType::EDGECONST);
    }

    if ($this->watcherPHIDs !== null) {
      $joins[] = qsprintf(
        $conn,
        'JOIN %T w ON w.src = role.phid AND w.type = %d',
        PhabricatorEdgeConfig::TABLE_NAME_EDGE,
        PhabricatorObjectHasWatcherEdgeType::EDGECONST);
    }

    if ($this->slugs !== null) {
      $joins[] = qsprintf(
        $conn,
        'JOIN %T slug on slug.rolePHID = role.phid',
        id(new PhabricatorRoleSlug())->getTableName());
    }

    if ($this->nameTokens !== null) {
      $name_tokens = $this->getNameTokensForQuery($this->nameTokens);
      foreach ($name_tokens as $key => $token) {
        $token_table = 'token_'.$key;
        $joins[] = qsprintf(
          $conn,
          'JOIN %T %T ON %T.roleID = role.id AND %T.token LIKE %>',
          PhabricatorRole::TABLE_DATASOURCE_TOKEN,
          $token_table,
          $token_table,
          $token_table,
          $token);
      }
    }

    return $joins;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorRoleApplication';
  }

  protected function getPrimaryTableAlias() {
    return 'role';
  }

  private function linkRoleGraph(array $roles, array $ancestors) {
    $ancestor_map = mpull($ancestors, null, 'getPHID');
    $roles_map = mpull($roles, null, 'getPHID');

    $all_map = $roles_map + $ancestor_map;

    $done = array();
    foreach ($roles as $key => $role) {
      $seen = array($role->getPHID() => true);

      if (!$this->linkRole($role, $all_map, $done, $seen)) {
        $this->didRejectResult($role);
        unset($roles[$key]);
        continue;
      }

      foreach ($role->getAncestorRoles() as $ancestor) {
        $seen[$ancestor->getPHID()] = true;
      }
    }

    return $roles;
  }

  private function linkRole($role, array $all, array $done, array $seen) {
    $parent_phid = $role->getParentRolePHID();

    // This role has no parent, so just attach `null` and return.
    if (!$parent_phid) {
      $role->attachParentRole(null);
      return true;
    }

    // This role has a parent, but it failed to load.
    if (empty($all[$parent_phid])) {
      return false;
    }

    // Test for graph cycles. If we encounter one, we're going to hide the
    // entire cycle since we can't meaningfully resolve it.
    if (isset($seen[$parent_phid])) {
      return false;
    }

    $seen[$parent_phid] = true;

    $parent = $all[$parent_phid];
    $role->attachParentRole($parent);

    if (!empty($done[$parent_phid])) {
      return true;
    }

    return $this->linkRole($parent, $all, $done, $seen);
  }

  private function getAllReachableAncestors(array $roles) {
    $ancestors = array();

    $seen = mpull($roles, null, 'getPHID');

    $stack = $roles;
    while ($stack) {
      $role = array_pop($stack);

      $phid = $role->getPHID();
      $ancestors[$phid] = $role;

      $parent_phid = $role->getParentRolePHID();
      if (!$parent_phid) {
        continue;
      }

      if (isset($seen[$parent_phid])) {
        continue;
      }

      $seen[$parent_phid] = true;
      $stack[] = $role->getParentRole();
    }

    return $ancestors;
  }

  private function loadSlugs(array $roles) {
    // Build a map from primary slugs to roles.
    $primary_map = array();
    foreach ($roles as $role) {
      $primary_slug = $role->getPrimarySlug();
      if ($primary_slug === null) {
        continue;
      }

      $primary_map[$primary_slug] = $role;
    }

    // Link up all of the queried slugs which correspond to primary
    // slugs. If we can link up everything from this (no slugs were queried,
    // or only primary slugs were queried) we don't need to load anything
    // else.
    $unknown = $this->slugNormals;
    foreach ($unknown as $input => $normal) {
      if (isset($primary_map[$input])) {
        $match = $input;
      } else if (isset($primary_map[$normal])) {
        $match = $normal;
      } else {
        continue;
      }

      $this->slugMap[$input] = array(
        'slug' => $match,
        'rolePHID' => $primary_map[$match]->getPHID(),
      );

      unset($unknown[$input]);
    }

    // If we need slugs, we have to load everything.
    // If we still have some queried slugs which we haven't mapped, we only
    // need to look for them.
    // If we've mapped everything, we don't have to do any work.
    $role_phids = mpull($roles, 'getPHID');
    if ($this->needSlugs) {
      $slugs = id(new PhabricatorRoleSlug())->loadAllWhere(
        'rolePHID IN (%Ls)',
        $role_phids);
    } else if ($unknown) {
      $slugs = id(new PhabricatorRoleSlug())->loadAllWhere(
        'rolePHID IN (%Ls) AND slug IN (%Ls)',
        $role_phids,
        $unknown);
    } else {
      $slugs = array();
    }

    // Link up any slugs we were not able to link up earlier.
    $extra_map = mpull($slugs, 'getRolePHID', 'getSlug');
    foreach ($unknown as $input => $normal) {
      if (isset($extra_map[$input])) {
        $match = $input;
      } else if (isset($extra_map[$normal])) {
        $match = $normal;
      } else {
        continue;
      }

      $this->slugMap[$input] = array(
        'slug' => $match,
        'rolePHID' => $extra_map[$match],
      );

      unset($unknown[$input]);
    }

    if ($this->needSlugs) {
      $slug_groups = mgroup($slugs, 'getRolePHID');
      foreach ($roles as $role) {
        $role_slugs = idx($slug_groups, $role->getPHID(), array());
        $role->attachSlugs($role_slugs);
      }
    }
  }

  private function getNameTokensForQuery(array $tokens) {
    // When querying for roles by name, only actually search for the five
    // longest tokens. MySQL can get grumpy with a large number of JOINs
    // with LIKEs and queries for more than 5 tokens are essentially never
    // legitimate searches for roles, but users copy/pasting nonsense.
    // See also PHI47.

    $length_map = array();
    foreach ($tokens as $token) {
      $length_map[$token] = strlen($token);
    }
    arsort($length_map);

    $length_map = array_slice($length_map, 0, 5, true);

    return array_keys($length_map);
  }

}
