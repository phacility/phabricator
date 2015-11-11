<?php

final class PhabricatorProjectQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $ids;
  private $phids;
  private $memberPHIDs;
  private $slugs;
  private $names;
  private $nameTokens;
  private $icons;
  private $colors;

  private $status       = 'status-any';
  const STATUS_ANY      = 'status-any';
  const STATUS_OPEN     = 'status-open';
  const STATUS_CLOSED   = 'status-closed';
  const STATUS_ACTIVE   = 'status-active';
  const STATUS_ARCHIVED = 'status-archived';

  private $needSlugs;
  private $needMembers;
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

  public function withMemberPHIDs(array $member_phids) {
    $this->memberPHIDs = $member_phids;
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

  public function needMembers($need_members) {
    $this->needMembers = $need_members;
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
    );
  }

  protected function getPagingValueMap($cursor, array $keys) {
    $project = $this->loadCursorObject($cursor);
    return array(
      'name' => $project->getName(),
    );
  }

  protected function loadPage() {
    $table = new PhabricatorProject();
    $data = $this->loadStandardPageRows($table);
    $projects = $table->loadAllFromArray($data);

    if ($projects) {
      $viewer_phid = $this->getViewer()->getPHID();
      $project_phids = mpull($projects, 'getPHID');

      $member_type = PhabricatorProjectProjectHasMemberEdgeType::EDGECONST;
      $watcher_type = PhabricatorObjectHasWatcherEdgeType::EDGECONST;

      $need_edge_types = array();
      if ($this->needMembers) {
        $need_edge_types[] = $member_type;
      } else {
        foreach ($data as $row) {
          $projects[$row['id']]->setIsUserMember(
            $viewer_phid,
            ($row['viewerIsMember'] !== null));
        }
      }

      if ($this->needWatchers) {
        $need_edge_types[] = $watcher_type;
      }

      if ($need_edge_types) {
        $edges = id(new PhabricatorEdgeQuery())
          ->withSourcePHIDs($project_phids)
          ->withEdgeTypes($need_edge_types)
          ->execute();

        if ($this->needMembers) {
          foreach ($projects as $project) {
            $phid = $project->getPHID();
            $project->attachMemberPHIDs(
              array_keys($edges[$phid][$member_type]));
            $project->setIsUserMember(
              $viewer_phid,
              isset($edges[$phid][$member_type][$viewer_phid]));
          }
        }

        if ($this->needWatchers) {
          foreach ($projects as $project) {
            $phid = $project->getPHID();
            $project->attachWatcherPHIDs(
              array_keys($edges[$phid][$watcher_type]));
            $project->setIsUserWatcher(
              $viewer_phid,
              isset($edges[$phid][$watcher_type][$viewer_phid]));
          }
        }
      }
    }

    return $projects;
  }

  protected function didFilterPage(array $projects) {
    if ($this->needImages) {
      $default = null;

      $file_phids = mpull($projects, 'getProfileImagePHID');
      $file_phids = array_filter($file_phids);
      if ($file_phids) {
        $files = id(new PhabricatorFileQuery())
          ->setParentQuery($this)
          ->setViewer($this->getViewer())
          ->withPHIDs($file_phids)
          ->execute();
        $files = mpull($files, null, 'getPHID');
      } else {
        $files = array();
      }

      foreach ($projects as $project) {
        $file = idx($files, $project->getProfileImagePHID());
        if (!$file) {
          if (!$default) {
            $default = PhabricatorFile::loadBuiltin(
              $this->getViewer(),
              'project.png');
          }
          $file = $default;
        }
        $project->attachProfileImageFile($file);
      }
    }

    if ($this->needSlugs) {
      $slugs = id(new PhabricatorProjectSlug())
        ->loadAllWhere(
          'projectPHID IN (%Ls)',
          mpull($projects, 'getPHID'));
      $slugs = mgroup($slugs, 'getProjectPHID');
      foreach ($projects as $project) {
        $project_slugs = idx($slugs, $project->getPHID(), array());
        $project->attachSlugs($project_slugs);
      }
    }

    return $projects;
  }

  protected function buildSelectClauseParts(AphrontDatabaseConnection $conn) {
    $select = parent::buildSelectClauseParts($conn);

    // NOTE: Because visibility checks for projects depend on whether or not
    // the user is a project member, we always load their membership. If we're
    // loading all members anyway we can piggyback on that; otherwise we
    // do an explicit join.
    if (!$this->needMembers) {
      $select[] = 'vm.dst viewerIsMember';
    }

    return $select;
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
        'status IN (%Ld)',
        $filter);
    }

    if ($this->ids !== null) {
      $where[] = qsprintf(
        $conn,
        'id IN (%Ld)',
        $this->ids);
    }

    if ($this->phids !== null) {
      $where[] = qsprintf(
        $conn,
        'phid IN (%Ls)',
        $this->phids);
    }

    if ($this->memberPHIDs !== null) {
      $where[] = qsprintf(
        $conn,
        'e.dst IN (%Ls)',
        $this->memberPHIDs);
    }

    if ($this->slugs !== null) {
      $where[] = qsprintf(
        $conn,
        'slug.slug IN (%Ls)',
        $this->slugs);
    }

    if ($this->names !== null) {
      $where[] = qsprintf(
        $conn,
        'name IN (%Ls)',
        $this->names);
    }

    if ($this->icons !== null) {
      $where[] = qsprintf(
        $conn,
        'icon IN (%Ls)',
        $this->icons);
    }

    if ($this->colors !== null) {
      $where[] = qsprintf(
        $conn,
        'color IN (%Ls)',
        $this->colors);
    }

    return $where;
  }

  protected function shouldGroupQueryResultRows() {
    if ($this->memberPHIDs || $this->nameTokens) {
      return true;
    }
    return parent::shouldGroupQueryResultRows();
  }

  protected function buildJoinClauseParts(AphrontDatabaseConnection $conn) {
    $joins = parent::buildJoinClauseParts($conn);

    if (!$this->needMembers !== null) {
      $joins[] = qsprintf(
        $conn,
        'LEFT JOIN %T vm ON vm.src = p.phid AND vm.type = %d AND vm.dst = %s',
        PhabricatorEdgeConfig::TABLE_NAME_EDGE,
        PhabricatorProjectProjectHasMemberEdgeType::EDGECONST,
        $this->getViewer()->getPHID());
    }

    if ($this->memberPHIDs !== null) {
      $joins[] = qsprintf(
        $conn,
        'JOIN %T e ON e.src = p.phid AND e.type = %d',
        PhabricatorEdgeConfig::TABLE_NAME_EDGE,
        PhabricatorProjectProjectHasMemberEdgeType::EDGECONST);
    }

    if ($this->slugs !== null) {
      $joins[] = qsprintf(
        $conn,
        'JOIN %T slug on slug.projectPHID = p.phid',
        id(new PhabricatorProjectSlug())->getTableName());
    }

    if ($this->nameTokens !== null) {
      foreach ($this->nameTokens as $key => $token) {
        $token_table = 'token_'.$key;
        $joins[] = qsprintf(
          $conn,
          'JOIN %T %T ON %T.projectID = p.id AND %T.token LIKE %>',
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
    return 'p';
  }

}
