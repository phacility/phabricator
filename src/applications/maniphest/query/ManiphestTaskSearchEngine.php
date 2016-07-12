<?php

final class ManiphestTaskSearchEngine
  extends PhabricatorApplicationSearchEngine {

  private $showBatchControls;
  private $baseURI;
  private $isBoardView;

  public function setIsBoardView($is_board_view) {
    $this->isBoardView = $is_board_view;
    return $this;
  }

  public function getIsBoardView() {
    return $this->isBoardView;
  }

  public function setBaseURI($base_uri) {
    $this->baseURI = $base_uri;
    return $this;
  }

  public function getBaseURI() {
    return $this->baseURI;
  }

  public function setShowBatchControls($show_batch_controls) {
    $this->showBatchControls = $show_batch_controls;
    return $this;
  }

  public function getResultTypeDescription() {
    return pht('Tasks');
  }

  public function getApplicationClassName() {
    return 'PhabricatorManiphestApplication';
  }

  public function newQuery() {
    return id(new ManiphestTaskQuery())
      ->needProjectPHIDs(true);
  }

  protected function buildCustomSearchFields() {
    return array(
      id(new PhabricatorOwnersSearchField())
        ->setLabel(pht('Assigned To'))
        ->setKey('assignedPHIDs')
        ->setConduitKey('assigned')
        ->setAliases(array('assigned'))
        ->setDescription(
          pht('Search for tasks owned by a user from a list.')),
      id(new PhabricatorUsersSearchField())
        ->setLabel(pht('Authors'))
        ->setKey('authorPHIDs')
        ->setAliases(array('author', 'authors'))
        ->setDescription(
          pht('Search for tasks with given authors.')),
      id(new PhabricatorSearchDatasourceField())
        ->setLabel(pht('Statuses'))
        ->setKey('statuses')
        ->setAliases(array('status'))
        ->setDescription(
          pht('Search for tasks with given statuses.'))
        ->setDatasource(new ManiphestTaskStatusFunctionDatasource()),
      id(new PhabricatorSearchDatasourceField())
        ->setLabel(pht('Priorities'))
        ->setKey('priorities')
        ->setAliases(array('priority'))
        ->setDescription(
          pht('Search for tasks with given priorities.'))
        ->setConduitParameterType(new ConduitIntListParameterType())
        ->setDatasource(new ManiphestTaskPriorityDatasource()),
      id(new PhabricatorSearchTextField())
        ->setLabel(pht('Contains Words'))
        ->setKey('fulltext'),
      id(new PhabricatorSearchThreeStateField())
        ->setLabel(pht('Blocking'))
        ->setKey('blocking')
        ->setOptions(
          pht('(Show All)'),
          pht('Show Only Tasks Blocking Other Tasks'),
          pht('Hide Tasks Blocking Other Tasks')),
      id(new PhabricatorSearchThreeStateField())
        ->setLabel(pht('Blocked'))
        ->setKey('blocked')
        ->setOptions(
          pht('(Show All)'),
          pht('Show Only Task Blocked By Other Tasks'),
          pht('Hide Tasks Blocked By Other Tasks')),
      id(new PhabricatorSearchSelectField())
        ->setLabel(pht('Group By'))
        ->setKey('group')
        ->setOptions($this->getGroupOptions()),
      id(new PhabricatorSearchDateField())
        ->setLabel(pht('Created After'))
        ->setKey('createdStart'),
      id(new PhabricatorSearchDateField())
        ->setLabel(pht('Created Before'))
        ->setKey('createdEnd'),
      id(new PhabricatorSearchDateField())
        ->setLabel(pht('Updated After'))
        ->setKey('modifiedStart'),
      id(new PhabricatorSearchDateField())
        ->setLabel(pht('Updated Before'))
        ->setKey('modifiedEnd'),
      id(new PhabricatorSearchTextField())
        ->setLabel(pht('Page Size'))
        ->setKey('limit'),
    );
  }

  protected function getDefaultFieldOrder() {
    return array(
      'assignedPHIDs',
      'projectPHIDs',
      'authorPHIDs',
      'subscriberPHIDs',
      'statuses',
      'priorities',
      'fulltext',
      'blocking',
      'blocked',
      'group',
      'order',
      'ids',
      '...',
      'createdStart',
      'createdEnd',
      'modifiedStart',
      'modifiedEnd',
      'limit',
    );
  }

  protected function getHiddenFields() {
    $keys = array();

    if ($this->getIsBoardView()) {
      $keys[] = 'group';
      $keys[] = 'order';
      $keys[] = 'limit';
    }

    return $keys;
  }

  protected function buildQueryFromParameters(array $map) {
    $query = $this->newQuery();

    if ($map['assignedPHIDs']) {
      $query->withOwners($map['assignedPHIDs']);
    }

    if ($map['authorPHIDs']) {
      $query->withAuthors($map['authorPHIDs']);
    }

    if ($map['statuses']) {
      $query->withStatuses($map['statuses']);
    }

    if ($map['priorities']) {
      $query->withPriorities($map['priorities']);
    }

    if ($map['createdStart']) {
      $query->withDateCreatedAfter($map['createdStart']);
    }

    if ($map['createdEnd']) {
      $query->withDateCreatedBefore($map['createdEnd']);
    }

    if ($map['modifiedStart']) {
      $query->withDateModifiedAfter($map['modifiedStart']);
    }

    if ($map['modifiedEnd']) {
      $query->withDateModifiedBefore($map['modifiedEnd']);
    }

    if ($map['blocking'] !== null) {
      $query->withBlockingTasks($map['blocking']);
    }

    if ($map['blocked'] !== null) {
      $query->withBlockedTasks($map['blocked']);
    }

    if (strlen($map['fulltext'])) {
      $query->withFullTextSearch($map['fulltext']);
    }

    $group = idx($map, 'group');
    $group = idx($this->getGroupValues(), $group);
    if ($group) {
      $query->setGroupBy($group);
    } else {
      $query->setGroupBy(head($this->getGroupValues()));
    }

    if ($map['ids']) {
      $ids = $map['ids'];
      foreach ($ids as $key => $id) {
        $id = trim($id, ' Tt');
        if (!$id || !is_numeric($id)) {
          unset($ids[$key]);
        } else {
          $ids[$key] = $id;
        }
      }

      if ($ids) {
        $query->withIDs($ids);
      }
    }

    return $query;
  }

  protected function getURI($path) {
    if ($this->baseURI) {
      return $this->baseURI.$path;
    }
    return '/maniphest/'.$path;
  }

  protected function getBuiltinQueryNames() {
    $names = array();

    if ($this->requireViewer()->isLoggedIn()) {
      $names['assigned'] = pht('Assigned');
      $names['authored'] = pht('Authored');
      $names['subscribed'] = pht('Subscribed');
    }

    $names['open'] = pht('Open Tasks');
    $names['all'] = pht('All Tasks');

    return $names;
  }

  public function buildSavedQueryFromBuiltin($query_key) {

    $query = $this->newSavedQuery();
    $query->setQueryKey($query_key);

    $viewer_phid = $this->requireViewer()->getPHID();

    switch ($query_key) {
      case 'all':
        return $query;
      case 'assigned':
        return $query
          ->setParameter('assignedPHIDs', array($viewer_phid))
          ->setParameter(
            'statuses',
            ManiphestTaskStatus::getOpenStatusConstants());
      case 'subscribed':
        return $query
          ->setParameter('subscriberPHIDs', array($viewer_phid))
          ->setParameter(
            'statuses',
            ManiphestTaskStatus::getOpenStatusConstants());
      case 'open':
        return $query
          ->setParameter(
            'statuses',
            ManiphestTaskStatus::getOpenStatusConstants());
      case 'authored':
        return $query
          ->setParameter('authorPHIDs', array($viewer_phid))
          ->setParameter('order', 'created')
          ->setParameter('group', 'none');
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
  }

  private function getGroupOptions() {
    return array(
      'priority' => pht('Priority'),
      'assigned' => pht('Assigned'),
      'status'   => pht('Status'),
      'project'  => pht('Project'),
      'none'     => pht('None'),
    );
  }

  private function getGroupValues() {
    return array(
      'priority' => ManiphestTaskQuery::GROUP_PRIORITY,
      'assigned' => ManiphestTaskQuery::GROUP_OWNER,
      'status'   => ManiphestTaskQuery::GROUP_STATUS,
      'project'  => ManiphestTaskQuery::GROUP_PROJECT,
      'none'     => ManiphestTaskQuery::GROUP_NONE,
    );
  }

  protected function renderResultList(
    array $tasks,
    PhabricatorSavedQuery $saved,
    array $handles) {

    $viewer = $this->requireViewer();

    if ($this->isPanelContext()) {
      $can_edit_priority = false;
      $can_bulk_edit = false;
    } else {
      $can_edit_priority = PhabricatorPolicyFilter::hasCapability(
        $viewer,
        $this->getApplication(),
        ManiphestEditPriorityCapability::CAPABILITY);

      $can_bulk_edit = PhabricatorPolicyFilter::hasCapability(
        $viewer,
        $this->getApplication(),
        ManiphestBulkEditCapability::CAPABILITY);
    }

    $list = id(new ManiphestTaskResultListView())
      ->setUser($viewer)
      ->setTasks($tasks)
      ->setSavedQuery($saved)
      ->setCanEditPriority($can_edit_priority)
      ->setCanBatchEdit($can_bulk_edit)
      ->setShowBatchControls($this->showBatchControls);

    $result = new PhabricatorApplicationSearchResultView();
    $result->setContent($list);

    return $result;
  }

  protected function willUseSavedQuery(PhabricatorSavedQuery $saved) {

    // The 'withUnassigned' parameter may be present in old saved queries from
    // before parameterized typeaheads, and is retained for compatibility. We
    // could remove it by migrating old saved queries.
    $assigned_phids = $saved->getParameter('assignedPHIDs', array());
    if ($saved->getParameter('withUnassigned')) {
      $assigned_phids[] = PhabricatorPeopleNoOwnerDatasource::FUNCTION_TOKEN;
    }
    $saved->setParameter('assignedPHIDs', $assigned_phids);

    // The 'projects' and other parameters may be present in old saved queries
    // from before parameterized typeaheads.
    $project_phids = $saved->getParameter('projectPHIDs', array());

    $old = $saved->getParameter('projects', array());
    foreach ($old as $phid) {
      $project_phids[] = $phid;
    }

    $all = $saved->getParameter('allProjectPHIDs', array());
    foreach ($all as $phid) {
      $project_phids[] = $phid;
    }

    $any = $saved->getParameter('anyProjectPHIDs', array());
    foreach ($any as $phid) {
      $project_phids[] = 'any('.$phid.')';
    }

    $not = $saved->getParameter('excludeProjectPHIDs', array());
    foreach ($not as $phid) {
      $project_phids[] = 'not('.$phid.')';
    }

    $users = $saved->getParameter('userProjectPHIDs', array());
    foreach ($users as $phid) {
      $project_phids[] = 'projects('.$phid.')';
    }

    $no = $saved->getParameter('withNoProject');
    if ($no) {
      $project_phids[] = 'null()';
    }

    $saved->setParameter('projectPHIDs', $project_phids);
  }

  protected function getNewUserBody() {
    $create_button = id(new PHUIButtonView())
      ->setTag('a')
      ->setText(pht('Create a Task'))
      ->setHref('/maniphest/task/edit/')
      ->setColor(PHUIButtonView::GREEN);

    $icon = $this->getApplication()->getIcon();
    $app_name =  $this->getApplication()->getName();
    $view = id(new PHUIBigInfoView())
      ->setIcon($icon)
      ->setTitle(pht('Welcome to %s', $app_name))
      ->setDescription(
        pht('Use Maniphest to track bugs, features, todos, or anything else '.
            'you need to get done. Tasks assigned to you will appear here.'))
      ->addAction($create_button);

      return $view;
  }

}
