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
    return pht('Maniphest Tasks');
  }

  public function getApplicationClassName() {
    return 'PhabricatorManiphestApplication';
  }

  public function newQuery() {
    return id(new ManiphestTaskQuery())
      ->needProjectPHIDs(true);
  }

  protected function buildCustomSearchFields() {
    // Hide the "Subtypes" constraint from the web UI if the install only
    // defines one task subtype, since it isn't of any use in this case.
    $subtype_map = id(new ManiphestTask())->newEditEngineSubtypeMap();
    $hide_subtypes = ($subtype_map->getCount() == 1);

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
      id(new PhabricatorSearchDatasourceField())
        ->setLabel(pht('Subtypes'))
        ->setKey('subtypes')
        ->setAliases(array('subtype'))
        ->setDescription(
          pht('Search for tasks with given subtypes.'))
        ->setDatasource(new ManiphestTaskSubtypeDatasource())
        ->setIsHidden($hide_subtypes),
      id(new PhabricatorPHIDsSearchField())
        ->setLabel(pht('Columns'))
        ->setKey('columnPHIDs')
        ->setAliases(array('column', 'columnPHID', 'columns')),
      id(new PhabricatorSearchThreeStateField())
        ->setLabel(pht('Open Parents'))
        ->setKey('hasParents')
        ->setAliases(array('blocking'))
        ->setOptions(
          pht('(Show All)'),
          pht('Show Only Tasks With Open Parents'),
          pht('Show Only Tasks Without Open Parents')),
      id(new PhabricatorSearchThreeStateField())
        ->setLabel(pht('Open Subtasks'))
        ->setKey('hasSubtasks')
        ->setAliases(array('blocked'))
        ->setOptions(
          pht('(Show All)'),
          pht('Show Only Tasks With Open Subtasks'),
          pht('Show Only Tasks Without Open Subtasks')),
      id(new PhabricatorIDsSearchField())
        ->setLabel(pht('Parent IDs'))
        ->setKey('parentIDs')
        ->setAliases(array('parentID')),
      id(new PhabricatorIDsSearchField())
        ->setLabel(pht('Subtask IDs'))
        ->setKey('subtaskIDs')
        ->setAliases(array('subtaskID')),
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
      id(new PhabricatorSearchDateField())
        ->setLabel(pht('Closed After'))
        ->setKey('closedStart'),
      id(new PhabricatorSearchDateField())
        ->setLabel(pht('Closed Before'))
        ->setKey('closedEnd'),
      id(new PhabricatorUsersSearchField())
        ->setLabel(pht('Closed By'))
        ->setKey('closerPHIDs')
        ->setAliases(array('closer', 'closerPHID', 'closers'))
        ->setDescription(pht('Search for tasks closed by certain users.')),
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
      'subtypes',
      'hasParents',
      'hasSubtasks',
      'parentIDs',
      'subtaskIDs',
      'group',
      'order',
      'ids',
      '...',
      'createdStart',
      'createdEnd',
      'modifiedStart',
      'modifiedEnd',
      'closedStart',
      'closedEnd',
      'closerPHIDs',
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

    if ($map['subtypes']) {
      $query->withSubtypes($map['subtypes']);
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

    if ($map['closedStart'] || $map['closedEnd']) {
      $query->withClosedEpochBetween($map['closedStart'], $map['closedEnd']);
    }

    if ($map['closerPHIDs']) {
      $query->withCloserPHIDs($map['closerPHIDs']);
    }

    if ($map['hasParents'] !== null) {
      $query->withOpenParents($map['hasParents']);
    }

    if ($map['hasSubtasks'] !== null) {
      $query->withOpenSubtasks($map['hasSubtasks']);
    }

    if ($map['parentIDs']) {
      $query->withParentTaskIDs($map['parentIDs']);
    }

    if ($map['subtaskIDs']) {
      $query->withSubtaskIDs($map['subtaskIDs']);
    }

    if ($map['columnPHIDs']) {
      $query->withColumnPHIDs($map['columnPHIDs']);
    }

    $group = idx($map, 'group');
    $group = idx($this->getGroupValues(), $group);
    if ($group) {
      $query->setGroupBy($group);
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
      $can_bulk_edit = false;
    } else {
      $can_bulk_edit = PhabricatorPolicyFilter::hasCapability(
        $viewer,
        $this->getApplication(),
        ManiphestBulkEditCapability::CAPABILITY);
    }

    $list = id(new ManiphestTaskResultListView())
      ->setUser($viewer)
      ->setTasks($tasks)
      ->setSavedQuery($saved)
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
    $viewer = $this->requireViewer();

    $create_button = id(new ManiphestEditEngine())
      ->setViewer($viewer)
      ->newNUXBUtton(pht('Create a Task'));

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


  protected function newExportFields() {
    $fields = array(
      id(new PhabricatorStringExportField())
        ->setKey('monogram')
        ->setLabel(pht('Monogram')),
      id(new PhabricatorPHIDExportField())
        ->setKey('authorPHID')
        ->setLabel(pht('Author PHID')),
      id(new PhabricatorStringExportField())
        ->setKey('author')
        ->setLabel(pht('Author')),
      id(new PhabricatorPHIDExportField())
        ->setKey('ownerPHID')
        ->setLabel(pht('Owner PHID')),
      id(new PhabricatorStringExportField())
        ->setKey('owner')
        ->setLabel(pht('Owner')),
      id(new PhabricatorStringExportField())
        ->setKey('status')
        ->setLabel(pht('Status')),
      id(new PhabricatorStringExportField())
        ->setKey('statusName')
        ->setLabel(pht('Status Name')),
      id(new PhabricatorEpochExportField())
        ->setKey('dateClosed')
        ->setLabel(pht('Date Closed')),
      id(new PhabricatorPHIDExportField())
        ->setKey('closerPHID')
        ->setLabel(pht('Closer PHID')),
      id(new PhabricatorStringExportField())
        ->setKey('closer')
        ->setLabel(pht('Closer')),
      id(new PhabricatorStringExportField())
        ->setKey('priority')
        ->setLabel(pht('Priority')),
      id(new PhabricatorStringExportField())
        ->setKey('priorityName')
        ->setLabel(pht('Priority Name')),
      id(new PhabricatorStringExportField())
        ->setKey('subtype')
        ->setLabel('Subtype'),
      id(new PhabricatorURIExportField())
        ->setKey('uri')
        ->setLabel(pht('URI')),
      id(new PhabricatorStringExportField())
        ->setKey('title')
        ->setLabel(pht('Title')),
      id(new PhabricatorStringExportField())
        ->setKey('description')
        ->setLabel(pht('Description')),
    );

    if (ManiphestTaskPoints::getIsEnabled()) {
      $fields[] = id(new PhabricatorDoubleExportField())
        ->setKey('points')
        ->setLabel('Points');
    }

    return $fields;
  }

  protected function newExportData(array $tasks) {
    $viewer = $this->requireViewer();

    $phids = array();
    foreach ($tasks as $task) {
      $phids[] = $task->getAuthorPHID();
      $phids[] = $task->getOwnerPHID();
      $phids[] = $task->getCloserPHID();
    }
    $handles = $viewer->loadHandles($phids);

    $export = array();
    foreach ($tasks as $task) {

      $author_phid = $task->getAuthorPHID();
      if ($author_phid) {
        $author_name = $handles[$author_phid]->getName();
      } else {
        $author_name = null;
      }

      $owner_phid = $task->getOwnerPHID();
      if ($owner_phid) {
        $owner_name = $handles[$owner_phid]->getName();
      } else {
        $owner_name = null;
      }

      $closer_phid = $task->getCloserPHID();
      if ($closer_phid) {
        $closer_name = $handles[$closer_phid]->getName();
      } else {
        $closer_name = null;
      }

      $status_value = $task->getStatus();
      $status_name = ManiphestTaskStatus::getTaskStatusName($status_value);

      $priority_value = $task->getPriority();
      $priority_name = ManiphestTaskPriority::getTaskPriorityName(
        $priority_value);

      $export[] = array(
        'monogram' => $task->getMonogram(),
        'authorPHID' => $author_phid,
        'author' => $author_name,
        'ownerPHID' => $owner_phid,
        'owner' => $owner_name,
        'status' => $status_value,
        'statusName' => $status_name,
        'priority' => $priority_value,
        'priorityName' => $priority_name,
        'points' => $task->getPoints(),
        'subtype' => $task->getSubtype(),
        'title' => $task->getTitle(),
        'uri' => PhabricatorEnv::getProductionURI($task->getURI()),
        'description' => $task->getDescription(),
        'dateClosed' => $task->getClosedEpoch(),
        'closerPHID' => $closer_phid,
        'closer' => $closer_name,
      );
    }

    return $export;
  }
}
