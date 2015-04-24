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

  public function getCustomFieldObject() {
    return new ManiphestTask();
  }

  public function buildSavedQueryFromRequest(AphrontRequest $request) {
    $saved = new PhabricatorSavedQuery();

    $saved->setParameter(
      'assignedPHIDs',
      $this->readUsersFromRequest($request, 'assigned'));

    $saved->setParameter(
      'authorPHIDs',
      $this->readUsersFromRequest($request, 'authors'));

    $saved->setParameter(
      'subscriberPHIDs',
      $this->readSubscribersFromRequest($request, 'subscribers'));

    $saved->setParameter(
      'statuses',
      $this->readListFromRequest($request, 'statuses'));

    $saved->setParameter(
      'priorities',
      $this->readListFromRequest($request, 'priorities'));

    $saved->setParameter(
      'blocking',
      $this->readBoolFromRequest($request, 'blocking'));
    $saved->setParameter(
      'blocked',
      $this->readBoolFromRequest($request, 'blocked'));

    $saved->setParameter('group', $request->getStr('group'));
    $saved->setParameter('order', $request->getStr('order'));

    $ids = $request->getStrList('ids');
    foreach ($ids as $key => $id) {
      $id = trim($id, ' Tt');
      if (!$id || !is_numeric($id)) {
        unset($ids[$key]);
      } else {
        $ids[$key] = $id;
      }
    }
    $saved->setParameter('ids', $ids);

    $saved->setParameter('fulltext', $request->getStr('fulltext'));

    $saved->setParameter(
      'projects',
      $this->readProjectsFromRequest($request, 'projects'));

    $saved->setParameter('createdStart', $request->getStr('createdStart'));
    $saved->setParameter('createdEnd', $request->getStr('createdEnd'));
    $saved->setParameter('modifiedStart', $request->getStr('modifiedStart'));
    $saved->setParameter('modifiedEnd', $request->getStr('modifiedEnd'));

    $limit = $request->getInt('limit');
    if ($limit > 0) {
      $saved->setParameter('limit', $limit);
    }

    $this->readCustomFieldsFromRequest($request, $saved);

    return $saved;
  }

  public function buildQueryFromSavedQuery(PhabricatorSavedQuery $saved) {
    $query = id(new ManiphestTaskQuery())
      ->needProjectPHIDs(true);

    $viewer = $this->requireViewer();

    $datasource = id(new PhabricatorPeopleUserFunctionDatasource())
      ->setViewer($viewer);

    $author_phids = $saved->getParameter('authorPHIDs', array());
    $author_phids = $datasource->evaluateTokens($author_phids);
    if ($author_phids) {
      $query->withAuthors($author_phids);
    }

    $datasource = id(new PhabricatorMetaMTAMailableFunctionDatasource())
      ->setViewer($viewer);
    $subscriber_phids = $saved->getParameter('subscriberPHIDs', array());
    $subscriber_phids = $datasource->evaluateTokens($subscriber_phids);
    if ($subscriber_phids) {
      $query->withSubscribers($subscriber_phids);
    }

    $datasource = id(new PhabricatorPeopleOwnerDatasource())
      ->setViewer($this->requireViewer());

    $assigned_phids = $this->readAssignedPHIDs($saved);
    $assigned_phids = $datasource->evaluateTokens($assigned_phids);
    if ($assigned_phids) {
      $query->withOwners($assigned_phids);
    }

    $datasource = id(new ManiphestTaskStatusFunctionDatasource())
      ->setViewer($this->requireViewer());
    $statuses = $saved->getParameter('statuses', array());
    $statuses = $datasource->evaluateTokens($statuses);
    if ($statuses) {
      $query->withStatuses($statuses);
    }

    $priorities = $saved->getParameter('priorities', array());
    if ($priorities) {
      $query->withPriorities($priorities);
    }


    $query->withBlockingTasks($saved->getParameter('blocking'));
    $query->withBlockedTasks($saved->getParameter('blocked'));

    $this->applyOrderByToQuery(
      $query,
      $this->getOrderValues(),
      $saved->getParameter('order'));

    $group = $saved->getParameter('group');
    $group = idx($this->getGroupValues(), $group);
    if ($group) {
      $query->setGroupBy($group);
    } else {
      $query->setGroupBy(head($this->getGroupValues()));
    }

    $ids = $saved->getParameter('ids');
    if ($ids) {
      $query->withIDs($ids);
    }

    $fulltext = $saved->getParameter('fulltext');
    if (strlen($fulltext)) {
      $query->withFullTextSearch($fulltext);
    }

    $projects = $this->readProjectTokens($saved);
    $adjusted = id(clone $saved)->setParameter('projects', $projects);
    $this->setQueryProjects($query, $adjusted);

    $start = $this->parseDateTime($saved->getParameter('createdStart'));
    $end = $this->parseDateTime($saved->getParameter('createdEnd'));

    if ($start) {
      $query->withDateCreatedAfter($start);
    }

    if ($end) {
      $query->withDateCreatedBefore($end);
    }

    $mod_start = $this->parseDateTime($saved->getParameter('modifiedStart'));
    $mod_end = $this->parseDateTime($saved->getParameter('modifiedEnd'));

    if ($mod_start) {
      $query->withDateModifiedAfter($mod_start);
    }

    if ($mod_end) {
      $query->withDateModifiedBefore($mod_end);
    }

    $this->applyCustomFieldsToQuery($query, $saved);

    return $query;
  }

  public function buildSearchForm(
    AphrontFormView $form,
    PhabricatorSavedQuery $saved) {

    $assigned_phids = $this->readAssignedPHIDs($saved);

    $author_phids = $saved->getParameter('authorPHIDs', array());
    $projects = $this->readProjectTokens($saved);

    $subscriber_phids = $saved->getParameter('subscriberPHIDs', array());

    $statuses = $saved->getParameter('statuses', array());
    $priorities = $saved->getParameter('priorities', array());

    $blocking_control = id(new AphrontFormSelectControl())
      ->setLabel(pht('Blocking'))
      ->setName('blocking')
      ->setValue($this->getBoolFromQuery($saved, 'blocking'))
      ->setOptions(array(
        '' => pht('Show All Tasks'),
        'true' => pht('Show Tasks Blocking Other Tasks'),
        'false' => pht('Show Tasks Not Blocking Other Tasks'),
      ));

    $blocked_control = id(new AphrontFormSelectControl())
      ->setLabel(pht('Blocked'))
      ->setName('blocked')
      ->setValue($this->getBoolFromQuery($saved, 'blocked'))
      ->setOptions(array(
        '' => pht('Show All Tasks'),
        'true' => pht('Show Tasks Blocked By Other Tasks'),
        'false' => pht('Show Tasks Not Blocked By Other Tasks'),
      ));

    $ids = $saved->getParameter('ids', array());

    $builtin_orders = $this->getOrderOptions();
    $custom_orders = $this->getCustomFieldOrderOptions();
    $all_orders = $builtin_orders + $custom_orders;

    $form
      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setDatasource(new PhabricatorPeopleOwnerDatasource())
          ->setName('assigned')
          ->setLabel(pht('Assigned To'))
          ->setValue($assigned_phids))
      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setDatasource(new PhabricatorProjectLogicalDatasource())
          ->setName('projects')
          ->setLabel(pht('Projects'))
          ->setValue($projects))
      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setDatasource(new PhabricatorPeopleUserFunctionDatasource())
          ->setName('authors')
          ->setLabel(pht('Authors'))
          ->setValue($author_phids))
      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setDatasource(new PhabricatorMetaMTAMailableFunctionDatasource())
          ->setName('subscribers')
          ->setLabel(pht('Subscribers'))
          ->setValue($subscriber_phids))
      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setDatasource(new ManiphestTaskStatusFunctionDatasource())
          ->setLabel(pht('Statuses'))
          ->setName('statuses')
          ->setValue($statuses))
      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setDatasource(new ManiphestTaskPriorityDatasource())
          ->setLabel(pht('Priorities'))
          ->setName('priorities')
          ->setValue($priorities))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setName('fulltext')
          ->setLabel(pht('Contains Words'))
          ->setValue($saved->getParameter('fulltext')))
      ->appendChild($blocking_control)
      ->appendChild($blocked_control);

    if (!$this->getIsBoardView()) {
      $form
        ->appendChild(
          id(new AphrontFormSelectControl())
            ->setName('group')
            ->setLabel(pht('Group By'))
            ->setValue($saved->getParameter('group'))
            ->setOptions($this->getGroupOptions()))
        ->appendChild(
          id(new AphrontFormSelectControl())
            ->setName('order')
            ->setLabel(pht('Order By'))
            ->setValue($saved->getParameter('order'))
            ->setOptions($all_orders));
    }

    $form
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setName('ids')
          ->setLabel(pht('Task IDs'))
          ->setValue(implode(', ', $ids)));

    $this->appendCustomFieldsToForm($form, $saved);

    $this->buildDateRange(
      $form,
      $saved,
      'createdStart',
      pht('Created After'),
      'createdEnd',
      pht('Created Before'));

    $this->buildDateRange(
      $form,
      $saved,
      'modifiedStart',
      pht('Updated After'),
      'modifiedEnd',
      pht('Updated Before'));

    if (!$this->getIsBoardView()) {
      $form
        ->appendChild(
          id(new AphrontFormTextControl())
            ->setName('limit')
            ->setLabel(pht('Page Size'))
            ->setValue($saved->getParameter('limit', 100)));
    }
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

  private function getOrderOptions() {
    return array(
      'priority' => pht('Priority'),
      'updated' => pht('Date Updated'),
      'created' => pht('Date Created'),
      'title' => pht('Title'),
    );
  }

  private function getOrderValues() {
    return array(
      'priority' => ManiphestTaskQuery::ORDER_PRIORITY,
      'updated'  => ManiphestTaskQuery::ORDER_MODIFIED,
      'created'  => ManiphestTaskQuery::ORDER_CREATED,
      'title'    => ManiphestTaskQuery::ORDER_TITLE,
    );
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

    return id(new ManiphestTaskResultListView())
      ->setUser($viewer)
      ->setTasks($tasks)
      ->setSavedQuery($saved)
      ->setCanEditPriority($can_edit_priority)
      ->setCanBatchEdit($can_bulk_edit)
      ->setShowBatchControls($this->showBatchControls);
  }

  private function readAssignedPHIDs(PhabricatorSavedQuery $saved) {
    $assigned_phids = $saved->getParameter('assignedPHIDs', array());

    // This may be present in old saved queries from before parameterized
    // typeaheads, and is retained for compatibility. We could remove it by
    // migrating old saved queries.
    if ($saved->getParameter('withUnassigned')) {
      $assigned_phids[] = PhabricatorPeopleNoOwnerDatasource::FUNCTION_TOKEN;
    }

    return $assigned_phids;
  }

  private function readProjectTokens(PhabricatorSavedQuery $saved) {
    $projects = $saved->getParameter('projects', array());

    $all = $saved->getParameter('allProjectPHIDs', array());
    foreach ($all as $phid) {
      $projects[] = $phid;
    }

    $any = $saved->getParameter('anyProjectPHIDs', array());
    foreach ($any as $phid) {
      $projects[] = 'any('.$phid.')';
    }

    $not = $saved->getParameter('excludeProjectPHIDs', array());
    foreach ($not as $phid) {
      $projects[] = 'not('.$phid.')';
    }

    $users = $saved->getParameter('userProjectPHIDs', array());
    foreach ($users as $phid) {
      $projects[] = 'projects('.$phid.')';
    }

    $no = $saved->getParameter('withNoProject');
    if ($no) {
      $projects[] = 'null()';
    }

    return $projects;
  }

}
