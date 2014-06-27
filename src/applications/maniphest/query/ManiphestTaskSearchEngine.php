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
    return 'PhabricatorApplicationManiphest';
  }

  public function getCustomFieldObject() {
    return new ManiphestTask();
  }

  public function buildSavedQueryFromRequest(AphrontRequest $request) {
    $saved = new PhabricatorSavedQuery();

    $saved->setParameter(
      'assignedPHIDs',
      $this->readUsersFromRequest($request, 'assigned'));

    $saved->setParameter('withUnassigned', $request->getBool('withUnassigned'));

    $saved->setParameter(
      'authorPHIDs',
      $this->readUsersFromRequest($request, 'authors'));

    $saved->setParameter(
      'subscriberPHIDs',
      $this->readPHIDsFromRequest($request, 'subscribers'));

    $saved->setParameter(
      'statuses',
      $this->readListFromRequest($request, 'statuses'));

    $saved->setParameter(
      'priorities',
      $this->readListFromRequest($request, 'priorities'));

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
      'allProjectPHIDs',
      $this->readPHIDsFromRequest($request, 'allProjects'));

    $saved->setParameter(
      'withNoProject',
      $request->getBool('withNoProject'));

    $saved->setParameter(
      'anyProjectPHIDs',
      $this->readPHIDsFromRequest($request, 'anyProjects'));

    $saved->setParameter(
      'excludeProjectPHIDs',
      $this->readPHIDsFromRequest($request, 'excludeProjects'));

    $saved->setParameter(
      'userProjectPHIDs',
      $this->readUsersFromRequest($request, 'userProjects'));

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
    $query = id(new ManiphestTaskQuery());

    $author_phids = $saved->getParameter('authorPHIDs');
    if ($author_phids) {
      $query->withAuthors($author_phids);
    }

    $subscriber_phids = $saved->getParameter('subscriberPHIDs');
    if ($subscriber_phids) {
      $query->withSubscribers($subscriber_phids);
    }

    $with_unassigned = $saved->getParameter('withUnassigned');
    if ($with_unassigned) {
      $query->withOwners(array(null));
    } else {
      $assigned_phids = $saved->getParameter('assignedPHIDs', array());
      if ($assigned_phids) {
        $query->withOwners($assigned_phids);
      }
    }

    $statuses = $saved->getParameter('statuses');
    if ($statuses) {
      $query->withStatuses($statuses);
    }

    $priorities = $saved->getParameter('priorities');
    if ($priorities) {
      $query->withPriorities($priorities);
    }

    $order = $saved->getParameter('order');
    $order = idx($this->getOrderValues(), $order);
    if ($order) {
      $query->setOrderBy($order);
    } else {
      $query->setOrderBy(head($this->getOrderValues()));
    }

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

    $with_no_project = $saved->getParameter('withNoProject');
    if ($with_no_project) {
      $query->withAllProjects(array(ManiphestTaskOwner::PROJECT_NO_PROJECT));
    } else {
      $project_phids = $saved->getParameter('allProjectPHIDs');
      if ($project_phids) {
        $query->withAllProjects($project_phids);
      }
    }

    $any_project_phids = $saved->getParameter('anyProjectPHIDs');
    if ($any_project_phids) {
      $query->withAnyProjects($any_project_phids);
    }

    $exclude_project_phids = $saved->getParameter('excludeProjectPHIDs');
    if ($exclude_project_phids) {
      $query->withoutProjects($exclude_project_phids);
    }

    $user_project_phids = $saved->getParameter('userProjectPHIDs');
    if ($user_project_phids) {
      $query->withAnyUserProjects($user_project_phids);
    }

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

    $assigned_phids = $saved->getParameter('assignedPHIDs', array());
    $author_phids = $saved->getParameter('authorPHIDs', array());
    $all_project_phids = $saved->getParameter(
      'allProjectPHIDs',
      array());
    $any_project_phids = $saved->getParameter(
      'anyProjectPHIDs',
      array());
    $exclude_project_phids = $saved->getParameter(
      'excludeProjectPHIDs',
      array());
    $user_project_phids = $saved->getParameter(
      'userProjectPHIDs',
      array());
    $subscriber_phids = $saved->getParameter('subscriberPHIDs', array());

    $all_phids = array_merge(
      $assigned_phids,
      $author_phids,
      $all_project_phids,
      $any_project_phids,
      $exclude_project_phids,
      $user_project_phids,
      $subscriber_phids);

    if ($all_phids) {
      $handles = id(new PhabricatorHandleQuery())
        ->setViewer($this->requireViewer())
        ->withPHIDs($all_phids)
        ->execute();
    } else {
      $handles = array();
    }

    $assigned_handles = array_select_keys($handles, $assigned_phids);
    $author_handles = array_select_keys($handles, $author_phids);
    $all_project_handles = array_select_keys($handles, $all_project_phids);
    $any_project_handles = array_select_keys($handles, $any_project_phids);
    $exclude_project_handles = array_select_keys(
      $handles,
      $exclude_project_phids);
    $user_project_handles = array_select_keys($handles, $user_project_phids);
    $subscriber_handles = array_select_keys($handles, $subscriber_phids);

    $with_unassigned = $saved->getParameter('withUnassigned');
    $with_no_projects = $saved->getParameter('withNoProject');

    $statuses = $saved->getParameter('statuses', array());
    $statuses = array_fuse($statuses);
    $status_control = id(new AphrontFormCheckboxControl())
      ->setLabel(pht('Status'));
    foreach (ManiphestTaskStatus::getTaskStatusMap() as $status => $name) {
      $status_control->addCheckbox(
        'statuses[]',
        $status,
        $name,
        isset($statuses[$status]));
    }

    $priorities = $saved->getParameter('priorities', array());
    $priorities = array_fuse($priorities);
    $priority_control = id(new AphrontFormCheckboxControl())
      ->setLabel(pht('Priority'));
    foreach (ManiphestTaskPriority::getTaskPriorityMap() as $pri => $name) {
      $priority_control->addCheckbox(
        'priorities[]',
        $pri,
        $name,
        isset($priorities[$pri]));
    }

    $ids = $saved->getParameter('ids', array());

    $form
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setDatasource('/typeahead/common/accounts/')
          ->setName('assigned')
          ->setLabel(pht('Assigned To'))
          ->setValue($assigned_handles))
      ->appendChild(
        id(new AphrontFormCheckboxControl())
          ->addCheckbox(
            'withUnassigned',
            1,
            pht('Show only unassigned tasks.'),
            $with_unassigned))
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setDatasource('/typeahead/common/projects/')
          ->setName('allProjects')
          ->setLabel(pht('In All Projects'))
          ->setValue($all_project_handles));

    if (!$this->getIsBoardView()) {
      $form
        ->appendChild(
          id(new AphrontFormCheckboxControl())
            ->addCheckbox(
              'withNoProject',
              1,
              pht('Show only tasks with no projects.'),
              $with_no_projects));
    }

    $form
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setDatasource('/typeahead/common/projects/')
          ->setName('anyProjects')
          ->setLabel(pht('In Any Project'))
          ->setValue($any_project_handles))
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setDatasource('/typeahead/common/projects/')
          ->setName('excludeProjects')
          ->setLabel(pht('Not In Projects'))
          ->setValue($exclude_project_handles))
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setDatasource('/typeahead/common/accounts/')
          ->setName('userProjects')
          ->setLabel(pht('In Users\' Projects'))
          ->setValue($user_project_handles))
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setDatasource('/typeahead/common/accounts/')
          ->setName('authors')
          ->setLabel(pht('Authors'))
          ->setValue($author_handles))
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setDatasource('/typeahead/common/mailable/')
          ->setName('subscribers')
          ->setLabel(pht('Subscribers'))
          ->setValue($subscriber_handles))
      ->appendChild($status_control)
      ->appendChild($priority_control);

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
            ->setOptions($this->getOrderOptions()));
    }

    $form
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setName('fulltext')
          ->setLabel(pht('Contains Words'))
          ->setValue($saved->getParameter('fulltext')))
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

  public function getBuiltinQueryNames() {
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
      'updated' => ManiphestTaskQuery::ORDER_MODIFIED,
      'created' => ManiphestTaskQuery::ORDER_CREATED,
      'title' => ManiphestTaskQuery::ORDER_TITLE,
    );
  }

  private function getGroupOptions() {
    return array(
      'priority' => pht('Priority'),
      'assigned' => pht('Assigned'),
      'status' => pht('Status'),
      'project' => pht('Project'),
      'none' => pht('None'),
    );
  }

  private function getGroupValues() {
    return array(
      'priority' => ManiphestTaskQuery::GROUP_PRIORITY,
      'assigned' => ManiphestTaskQuery::GROUP_OWNER,
      'status' => ManiphestTaskQuery::GROUP_STATUS,
      'project' => ManiphestTaskQuery::GROUP_PROJECT,
      'none' => ManiphestTaskQuery::GROUP_NONE,
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
        ManiphestCapabilityEditPriority::CAPABILITY);

      $can_bulk_edit = PhabricatorPolicyFilter::hasCapability(
        $viewer,
        $this->getApplication(),
        ManiphestCapabilityBulkEdit::CAPABILITY);
    }

    return id(new ManiphestTaskResultListView())
      ->setUser($viewer)
      ->setTasks($tasks)
      ->setSavedQuery($saved)
      ->setCanEditPriority($can_edit_priority)
      ->setCanBatchEdit($can_bulk_edit)
      ->setShowBatchControls($this->showBatchControls);
  }

}
