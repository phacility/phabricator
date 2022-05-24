<?php

final class PhabricatorProjectQuery
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
  private $hasSubprojects;
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

  public function withParentProjectPHIDs($parent_phids) {
    $this->parentPHIDs = $parent_phids;
    return $this;
  }

  public function withAncestorProjectPHIDs($ancestor_phids) {
    $this->ancestorPHIDs = $ancestor_phids;
    return $this;
  }

  public function withIsMilestone($is_milestone) {
    $this->isMilestone = $is_milestone;
    return $this;
  }

  public function withHasSubprojects($has_subprojects) {
    $this->hasSubprojects = $has_subprojects;
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
    return new PhabricatorProject();
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
        // for the slugs exactly as entered. This allows older projects with
        // slugs that are no longer valid to continue to work.
        $this->allSlugs[$slug] = $slug;
      }
    }
  }

  protected function willFilterPage(array $projects) {
    $ancestor_paths = array();
    foreach ($projects as $project) {
      foreach ($project->getAncestorProjectPaths() as $path) {
        $ancestor_paths[$path] = $path;
      }
    }

    if ($ancestor_paths) {
      $ancestors = id(new PhabricatorProject())->loadAllWhere(
        'projectPath IN (%Ls)',
        $ancestor_paths);
    } else {
      $ancestors = array();
    }

    $projects = $this->linkProjectGraph($projects, $ancestors);

    $viewer_phid = $this->getViewer()->getPHID();

    $material_type = PhabricatorProjectMaterializedMemberEdgeType::EDGECONST;
    $watcher_type = PhabricatorObjectHasWatcherEdgeType::EDGECONST;

    $types = array();
    $types[] = $material_type;
    if ($this->needWatchers) {
      $types[] = $watcher_type;
    }

    $all_graph = $this->getAllReachableAncestors($projects);

    // See T13484. If the graph is damaged (and contains a cycle or an edge
    // pointing at a project which has been destroyed), some of the nodes we
    // started with may be filtered out by reachability tests. If any of the
    // projects we are linking up don't have available ancestors, filter them
    // out.

    foreach ($projects as $key => $project) {
      $project_phid = $project->getPHID();
      if (!isset($all_graph[$project_phid])) {
        $this->didRejectResult($project);
        unset($projects[$key]);
        continue;
      }
    }

    if (!$projects) {
      return array();
    }

    // NOTE: Although we may not need much information about ancestors, we
    // always need to test if the viewer is a member, because we will return
    // ancestor projects to the policy filter via ExtendedPolicy calls. If
    // we skip populating membership data on a parent, the policy framework
    // will think the user is not a member of the parent project.

    $all_sources = array();
    foreach ($all_graph as $project) {
      // For milestones, we need parent members.
      if ($project->isMilestone()) {
        $parent_phid = $project->getParentProjectPHID();
        $all_sources[$parent_phid] = $parent_phid;
      }

      $phid = $project->getPHID();
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

    $membership_projects = array();
    foreach ($all_graph as $project) {
      $project_phid = $project->getPHID();

      if ($project->isMilestone()) {
        $source_phids = array($project->getParentProjectPHID());
      } else {
        $source_phids = array($project_phid);
      }

      if ($any_edges) {
        $member_phids = $edge_query->getDestinationPHIDs(
          $source_phids,
          array($material_type));
      } else {
        $member_phids = array();
      }

      if (in_array($viewer_phid, $member_phids)) {
        $membership_projects[$project_phid] = $project;
      }

      if ($this->needMembers || $this->needAncestorMembers) {
        $project->attachMemberPHIDs($member_phids);
      }

      if ($this->needWatchers) {
        $watcher_phids = $edge_query->getDestinationPHIDs(
          array($project_phid),
          array($watcher_type));
        $project->attachWatcherPHIDs($watcher_phids);
        $project->setIsUserWatcher(
          $viewer_phid,
          in_array($viewer_phid, $watcher_phids));
      }
    }

    // If we loaded ancestor members, we've already populated membership
    // lists above, so we can skip this step.
    if (!$this->needAncestorMembers) {
      $member_graph = $this->getAllReachableAncestors($membership_projects);

      foreach ($all_graph as $phid => $project) {
        $is_member = isset($member_graph[$phid]);
        $project->setIsUserMember($viewer_phid, $is_member);
      }
    }

    return $projects;
  }

  protected function didFilterPage(array $projects) {
    $viewer = $this->getViewer();

    if ($this->needImages) {
      $need_images = $projects;

      // First, try to load custom profile images for any projects with custom
      // images.
      $file_phids = array();
      foreach ($need_images as $key => $project) {
        $image_phid = $project->getProfileImagePHID();
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

      // For projects with default images, or projects where the custom image
      // failed to load, load a builtin image.
      if ($need_images) {
        $builtin_map = array();
        $builtins = array();
        foreach ($need_images as $key => $project) {
          $icon = $project->getIcon();

          $builtin_name = PhabricatorProjectIconSet::getIconImage($icon);
          $builtin_name = 'projects/'.$builtin_name;

          $builtin = id(new PhabricatorFilesOnDiskBuiltinFile())
            ->setName($builtin_name);

          $builtin_key = $builtin->getBuiltinFileKey();

          $builtins[] = $builtin;
          $builtin_map[$key] = $builtin_key;
        }

        $builtin_files = PhabricatorFile::loadBuiltins(
          $viewer,
          $builtins);

        foreach ($need_images as $key => $project) {
          $builtin_key = $builtin_map[$key];
          $builtin_file = $builtin_files[$builtin_key];
          $project->attachProfileImageFile($builtin_file);
        }
      }
    }

    $this->loadSlugs($projects);

    return $projects;
  }

  protected function buildWhereClauseParts(AphrontDatabaseConnection $conn) {
    $where = parent::buildWhereClauseParts($conn);

    if ($this->status != self::STATUS_ANY) {
      switch ($this->status) {
        case self::STATUS_OPEN:
        case self::STATUS_ACTIVE:
          $filter = array(
            PhabricatorProjectStatus::STATUS_ACTIVE,
          );
          break;
        case self::STATUS_CLOSED:
        case self::STATUS_ARCHIVED:
          $filter = array(
            PhabricatorProjectStatus::STATUS_ARCHIVED,
          );
          break;
        default:
          throw new Exception(
            pht(
              "Unknown project status '%s'!",
              $this->status));
      }
      $where[] = qsprintf(
        $conn,
        'project.status IN (%Ld)',
        $filter);
    }

    if ($this->statuses !== null) {
      $where[] = qsprintf(
        $conn,
        'project.status IN (%Ls)',
        $this->statuses);
    }

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'project.id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn,
        'project.phid IN (%Ls)',
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
        'project.name IN (%Ls)',
        $this->names);
    }

    if ($this->namePrefixes) {
      $parts = array();
      foreach ($this->namePrefixes as $name_prefix) {
        $parts[] = qsprintf(
          $conn,
          'project.name LIKE %>',
          $name_prefix);
      }
      $where[] = qsprintf($conn, '%LO', $parts);
    }

    if ($this->icons !== null) {
      $where[] = qsprintf(
        $conn,
        'project.icon IN (%Ls)',
        $this->icons);
    }

    if ($this->colors !== null) {
      $where[] = qsprintf(
        $conn,
        'project.color IN (%Ls)',
        $this->colors);
    }

    if ($this->parentPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'project.parentProjectPHID IN (%Ls)',
        $this->parentPHIDs);
    }

    if ($this->ancestorPHIDs !== null) {
      $ancestor_paths = queryfx_all(
        $conn,
        'SELECT projectPath, projectDepth FROM %T WHERE phid IN (%Ls)',
        id(new PhabricatorProject())->getTableName(),
        $this->ancestorPHIDs);
      if (!$ancestor_paths) {
        throw new PhabricatorEmptyQueryException();
      }

      $sql = array();
      foreach ($ancestor_paths as $ancestor_path) {
        $sql[] = qsprintf(
          $conn,
          '(project.projectPath LIKE %> AND project.projectDepth > %d)',
          $ancestor_path['projectPath'],
          $ancestor_path['projectDepth']);
      }

      $where[] = qsprintf($conn, '%LO', $sql);

      $where[] = qsprintf(
        $conn,
        'project.parentProjectPHID IS NOT NULL');
    }

    if ($this->isMilestone !== null) {
      if ($this->isMilestone) {
        $where[] = qsprintf(
          $conn,
          'project.milestoneNumber IS NOT NULL');
      } else {
        $where[] = qsprintf(
          $conn,
          'project.milestoneNumber IS NULL');
      }
    }


    if ($this->hasSubprojects !== null) {
      $where[] = qsprintf(
        $conn,
        'project.hasSubprojects = %d',
        (int)$this->hasSubprojects);
    }

    if ($this->minDepth !== null) {
      $where[] = qsprintf(
        $conn,
        'project.projectDepth >= %d',
        $this->minDepth);
    }

    if ($this->maxDepth !== null) {
      $where[] = qsprintf(
        $conn,
        'project.projectDepth <= %d',
        $this->maxDepth);
    }

    if ($this->minMilestoneNumber !== null) {
      $where[] = qsprintf(
        $conn,
        'project.milestoneNumber >= %d',
        $this->minMilestoneNumber);
    }

    if ($this->maxMilestoneNumber !== null) {
      $where[] = qsprintf(
        $conn,
        'project.milestoneNumber <= %d',
        $this->maxMilestoneNumber);
    }

    if ($this->subtypes !== null) {
      $where[] = qsprintf(
        $conn,
        'project.subtype IN (%Ls)',
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
        'JOIN %T e ON e.src = project.phid AND e.type = %d',
        PhabricatorEdgeConfig::TABLE_NAME_EDGE,
        PhabricatorProjectMaterializedMemberEdgeType::EDGECONST);
    }

    if ($this->watcherPHIDs !== null) {
      $joins[] = qsprintf(
        $conn,
        'JOIN %T w ON w.src = project.phid AND w.type = %d',
        PhabricatorEdgeConfig::TABLE_NAME_EDGE,
        PhabricatorObjectHasWatcherEdgeType::EDGECONST);
    }

    if ($this->slugs !== null) {
      $joins[] = qsprintf(
        $conn,
        'JOIN %T slug on slug.projectPHID = project.phid',
        id(new PhabricatorProjectSlug())->getTableName());
    }

    if ($this->nameTokens !== null) {
      $name_tokens = $this->getNameTokensForQuery($this->nameTokens);
      foreach ($name_tokens as $key => $token) {
        $token_table = 'token_'.$key;
        $joins[] = qsprintf(
          $conn,
          'JOIN %T %T ON %T.projectID = project.id AND %T.token LIKE %>',
          PhabricatorProject::TABLE_DATASOURCE_TOKEN,
          $token_table,
          $token_table,
          $token_table,
          $token);
      }
    }

    return $joins;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorProjectApplication';
  }

  protected function getPrimaryTableAlias() {
    return 'project';
  }

  private function linkProjectGraph(array $projects, array $ancestors) {
    $ancestor_map = mpull($ancestors, null, 'getPHID');
    $projects_map = mpull($projects, null, 'getPHID');

    $all_map = $projects_map + $ancestor_map;

    $done = array();
    foreach ($projects as $key => $project) {
      $seen = array($project->getPHID() => true);

      if (!$this->linkProject($project, $all_map, $done, $seen)) {
        $this->didRejectResult($project);
        unset($projects[$key]);
        continue;
      }

      foreach ($project->getAncestorProjects() as $ancestor) {
        $seen[$ancestor->getPHID()] = true;
      }
    }

    return $projects;
  }

  private function linkProject($project, array $all, array $done, array $seen) {
    $parent_phid = $project->getParentProjectPHID();

    // This project has no parent, so just attach `null` and return.
    if (!$parent_phid) {
      $project->attachParentProject(null);
      return true;
    }

    // This project has a parent, but it failed to load.
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
    $project->attachParentProject($parent);

    if (!empty($done[$parent_phid])) {
      return true;
    }

    return $this->linkProject($parent, $all, $done, $seen);
  }

  private function getAllReachableAncestors(array $projects) {
    $ancestors = array();

    $seen = mpull($projects, null, 'getPHID');

    $stack = $projects;
    while ($stack) {
      $project = array_pop($stack);

      $phid = $project->getPHID();
      $ancestors[$phid] = $project;

      $parent_phid = $project->getParentProjectPHID();
      if (!$parent_phid) {
        continue;
      }

      if (isset($seen[$parent_phid])) {
        continue;
      }

      $seen[$parent_phid] = true;
      $stack[] = $project->getParentProject();
    }

    return $ancestors;
  }

  private function loadSlugs(array $projects) {
    // Build a map from primary slugs to projects.
    $primary_map = array();
    foreach ($projects as $project) {
      $primary_slug = $project->getPrimarySlug();
      if ($primary_slug === null) {
        continue;
      }

      $primary_map[$primary_slug] = $project;
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
        'projectPHID' => $primary_map[$match]->getPHID(),
      );

      unset($unknown[$input]);
    }

    // If we need slugs, we have to load everything.
    // If we still have some queried slugs which we haven't mapped, we only
    // need to look for them.
    // If we've mapped everything, we don't have to do any work.
    $project_phids = mpull($projects, 'getPHID');
    if ($this->needSlugs) {
      $slugs = id(new PhabricatorProjectSlug())->loadAllWhere(
        'projectPHID IN (%Ls)',
        $project_phids);
    } else if ($unknown) {
      $slugs = id(new PhabricatorProjectSlug())->loadAllWhere(
        'projectPHID IN (%Ls) AND slug IN (%Ls)',
        $project_phids,
        $unknown);
    } else {
      $slugs = array();
    }

    // Link up any slugs we were not able to link up earlier.
    $extra_map = mpull($slugs, 'getProjectPHID', 'getSlug');
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
        'projectPHID' => $extra_map[$match],
      );

      unset($unknown[$input]);
    }

    if ($this->needSlugs) {
      $slug_groups = mgroup($slugs, 'getProjectPHID');
      foreach ($projects as $project) {
        $project_slugs = idx($slug_groups, $project->getPHID(), array());
        $project->attachSlugs($project_slugs);
      }
    }
  }

  private function getNameTokensForQuery(array $tokens) {
    // When querying for projects by name, only actually search for the five
    // longest tokens. MySQL can get grumpy with a large number of JOINs
    // with LIKEs and queries for more than 5 tokens are essentially never
    // legitimate searches for projects, but users copy/pasting nonsense.
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
