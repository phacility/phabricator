<?php

final class PhabricatorProjectSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Projects');
  }

  public function getApplicationClassName() {
    return 'PhabricatorProjectApplication';
  }

  public function newQuery() {
    return id(new PhabricatorProjectQuery())
      ->needImages(true)
      ->needMembers(true)
      ->needWatchers(true);
  }

  protected function buildCustomSearchFields() {
    $subtype_map = id(new PhabricatorProject())->newEditEngineSubtypeMap();
    $hide_subtypes = ($subtype_map->getCount() == 1);

    return array(
      id(new PhabricatorSearchTextField())
        ->setLabel(pht('Name'))
        ->setKey('name')
        ->setDescription(
          pht(
            '(Deprecated.) Search for projects with a given name or '.
            'hashtag using tokenizer/datasource query matching rules. This '.
            'is deprecated in favor of the more powerful "query" '.
            'constraint.')),
      id(new PhabricatorSearchStringListField())
        ->setLabel(pht('Slugs'))
        ->setIsHidden(true)
        ->setKey('slugs')
        ->setDescription(
          pht(
            'Search for projects with particular slugs. (Slugs are the same '.
            'as project hashtags.)')),
      id(new PhabricatorUsersSearchField())
        ->setLabel(pht('Members'))
        ->setKey('memberPHIDs')
        ->setConduitKey('members')
        ->setAliases(array('member', 'members')),
      id(new PhabricatorUsersSearchField())
        ->setLabel(pht('Watchers'))
        ->setKey('watcherPHIDs')
        ->setConduitKey('watchers')
        ->setAliases(array('watcher', 'watchers')),
      id(new PhabricatorSearchSelectField())
        ->setLabel(pht('Status'))
        ->setKey('status')
        ->setOptions($this->getStatusOptions()),
      id(new PhabricatorSearchThreeStateField())
        ->setLabel(pht('Milestones'))
        ->setKey('isMilestone')
        ->setOptions(
          pht('(Show All)'),
          pht('Show Only Milestones'),
          pht('Hide Milestones'))
        ->setDescription(
          pht(
            'Pass true to find only milestones, or false to omit '.
            'milestones.')),
      id(new PhabricatorSearchThreeStateField())
        ->setLabel(pht('Root Projects'))
        ->setKey('isRoot')
        ->setOptions(
          pht('(Show All)'),
          pht('Show Only Root Projects'),
          pht('Hide Root Projects'))
        ->setDescription(
          pht(
            'Pass true to find only root projects, or false to omit '.
            'root projects.')),
      id(new PhabricatorSearchIntField())
        ->setLabel(pht('Minimum Depth'))
        ->setKey('minDepth')
        ->setIsHidden(true)
        ->setDescription(
          pht(
            'Find projects with a given minimum depth. Root projects '.
            'have depth 0, their immediate children have depth 1, and '.
            'so on.')),
      id(new PhabricatorSearchIntField())
        ->setLabel(pht('Maximum Depth'))
        ->setKey('maxDepth')
        ->setIsHidden(true)
        ->setDescription(
          pht(
            'Find projects with a given maximum depth. Root projects '.
            'have depth 0, their immediate children have depth 1, and '.
            'so on.')),
      id(new PhabricatorSearchDatasourceField())
        ->setLabel(pht('Subtypes'))
        ->setKey('subtypes')
        ->setAliases(array('subtype'))
        ->setDescription(
          pht('Search for projects with given subtypes.'))
        ->setDatasource(new PhabricatorProjectSubtypeDatasource())
        ->setIsHidden($hide_subtypes),
      id(new PhabricatorSearchCheckboxesField())
        ->setLabel(pht('Icons'))
        ->setKey('icons')
        ->setOptions($this->getIconOptions()),
      id(new PhabricatorSearchCheckboxesField())
        ->setLabel(pht('Colors'))
        ->setKey('colors')
        ->setOptions($this->getColorOptions()),
      id(new PhabricatorPHIDsSearchField())
        ->setLabel(pht('Parent Projects'))
        ->setKey('parentPHIDs')
        ->setConduitKey('parents')
        ->setAliases(array('parent', 'parents', 'parentPHID'))
        ->setDescription(pht('Find direct subprojects of specified parents.')),
      id(new PhabricatorPHIDsSearchField())
        ->setLabel(pht('Ancestor Projects'))
        ->setKey('ancestorPHIDs')
        ->setConduitKey('ancestors')
        ->setAliases(array('ancestor', 'ancestors', 'ancestorPHID'))
        ->setDescription(
          pht('Find all subprojects beneath specified ancestors.')),
    );
  }


  protected function buildQueryFromParameters(array $map) {
    $query = $this->newQuery();

    if (strlen($map['name'])) {
      $tokens = PhabricatorTypeaheadDatasource::tokenizeString($map['name']);
      $query->withNameTokens($tokens);
    }

    if ($map['slugs']) {
      $query->withSlugs($map['slugs']);
    }

    if ($map['memberPHIDs']) {
      $query->withMemberPHIDs($map['memberPHIDs']);
    }

    if ($map['watcherPHIDs']) {
      $query->withWatcherPHIDs($map['watcherPHIDs']);
    }

    if ($map['status']) {
      $status = idx($this->getStatusValues(), $map['status']);
      if ($status) {
        $query->withStatus($status);
      }
    }

    if ($map['icons']) {
      $query->withIcons($map['icons']);
    }

    if ($map['colors']) {
      $query->withColors($map['colors']);
    }

    if ($map['isMilestone'] !== null) {
      $query->withIsMilestone($map['isMilestone']);
    }

    $min_depth = $map['minDepth'];
    $max_depth = $map['maxDepth'];

    if ($min_depth !== null || $max_depth !== null) {
      if ($min_depth !== null && $max_depth !== null) {
        if ($min_depth > $max_depth) {
          throw new Exception(
            pht(
              'Search constraint "minDepth" must be no larger than '.
              'search constraint "maxDepth".'));
        }
      }
    }

    if ($map['isRoot'] !== null) {
      if ($map['isRoot']) {
        if ($max_depth === null) {
          $max_depth = 0;
        } else {
          $max_depth = min(0, $max_depth);
        }

        $query->withDepthBetween(null, 0);
      } else {
        if ($min_depth === null) {
          $min_depth = 1;
        } else {
          $min_depth = max($min_depth, 1);
        }
      }
    }

    if ($min_depth !== null || $max_depth !== null) {
      $query->withDepthBetween($min_depth, $max_depth);
    }

    if ($map['parentPHIDs']) {
      $query->withParentProjectPHIDs($map['parentPHIDs']);
    }

    if ($map['ancestorPHIDs']) {
      $query->withAncestorProjectPHIDs($map['ancestorPHIDs']);
    }

    if ($map['subtypes']) {
      $query->withSubtypes($map['subtypes']);
    }

    return $query;
  }

  protected function getURI($path) {
    return '/project/'.$path;
  }

  protected function getBuiltinQueryNames() {
    $names = array();

    if ($this->requireViewer()->isLoggedIn()) {
      $names['joined'] = pht('Joined');
    }

    if ($this->requireViewer()->isLoggedIn()) {
      $names['watching'] = pht('Watching');
    }

    $names['active'] = pht('Active');
    $names['all'] = pht('All');

    return $names;
  }

  public function buildSavedQueryFromBuiltin($query_key) {
    $query = $this->newSavedQuery();
    $query->setQueryKey($query_key);

    $viewer_phid = $this->requireViewer()->getPHID();

    // By default, do not show milestones in the list view.
    $query->setParameter('isMilestone', false);

    switch ($query_key) {
      case 'all':
        return $query;
      case 'active':
        return $query
          ->setParameter('status', 'active');
      case 'joined':
        return $query
          ->setParameter('memberPHIDs', array($viewer_phid))
          ->setParameter('status', 'active');
      case 'watching':
        return $query
          ->setParameter('watcherPHIDs', array($viewer_phid))
          ->setParameter('status', 'active');
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
  }

  private function getStatusOptions() {
    return array(
      'active'   => pht('Show Only Active Projects'),
      'archived' => pht('Show Only Archived Projects'),
      'all'      => pht('Show All Projects'),
    );
  }

  private function getStatusValues() {
    return array(
      'active'   => PhabricatorProjectQuery::STATUS_ACTIVE,
      'archived' => PhabricatorProjectQuery::STATUS_ARCHIVED,
      'all'      => PhabricatorProjectQuery::STATUS_ANY,
    );
  }

  private function getIconOptions() {
    $options = array();

    $set = new PhabricatorProjectIconSet();
    foreach ($set->getIcons() as $icon) {
      if ($icon->getIsDisabled()) {
        continue;
      }

      $options[$icon->getKey()] = array(
        id(new PHUIIconView())
          ->setIcon($icon->getIcon()),
        ' ',
        $icon->getLabel(),
      );
    }

    return $options;
  }

  private function getColorOptions() {
    $options = array();

    foreach (PhabricatorProjectIconSet::getColorMap() as $color => $name) {
      $options[$color] = array(
        id(new PHUITagView())
          ->setType(PHUITagView::TYPE_SHADE)
          ->setColor($color)
          ->setName($name),
      );
    }

    return $options;
  }

  protected function renderResultList(
    array $projects,
    PhabricatorSavedQuery $query,
    array $handles) {
    assert_instances_of($projects, 'PhabricatorProject');
    $viewer = $this->requireViewer();

    $list = id(new PhabricatorProjectListView())
      ->setUser($viewer)
      ->setProjects($projects)
      ->setShowWatching(true)
      ->setShowMember(true)
      ->renderList();

    return id(new PhabricatorApplicationSearchResultView())
      ->setObjectList($list)
      ->setNoDataString(pht('No projects found.'));
  }

  protected function getNewUserBody() {
    $create_button = id(new PHUIButtonView())
      ->setTag('a')
      ->setText(pht('Create a Project'))
      ->setHref('/project/edit/')
      ->setColor(PHUIButtonView::GREEN);

    $icon = $this->getApplication()->getIcon();
    $app_name =  $this->getApplication()->getName();
    $view = id(new PHUIBigInfoView())
      ->setIcon($icon)
      ->setTitle(pht('Welcome to %s', $app_name))
      ->setDescription(
        pht('Projects are flexible storage containers used as '.
            'tags, teams, projects, or anything you need to group.'))
      ->addAction($create_button);

      return $view;
  }

}
