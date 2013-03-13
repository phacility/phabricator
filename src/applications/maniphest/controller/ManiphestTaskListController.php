<?php

/**
 * @group maniphest
 */
final class ManiphestTaskListController extends ManiphestController {

  const DEFAULT_PAGE_SIZE = 1000;

  private $view;

  public function willProcessRequest(array $data) {
    $this->view = idx($data, 'view');
  }

  private function getArrToStrList($key) {
    $arr = $this->getRequest()->getArr($key);
    $arr = implode(',', $arr);
    return nonempty($arr, null);
  }

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    if ($request->isFormPost()) {
      // Redirect to GET so URIs can be copy/pasted.

      $task_ids   = $request->getStr('set_tasks');
      $task_ids   = nonempty($task_ids, null);

      $search_text = $request->getStr('set_search');

      $min_priority = $request->getInt('set_lpriority');

      $max_priority = $request->getInt('set_hpriority');

      $uri = $request->getRequestURI()
        ->alter('users',      $this->getArrToStrList('set_users'))
        ->alter('projects',   $this->getArrToStrList('set_projects'))
        ->alter('aprojects',  $this->getArrToStrList('set_aprojects'))
        ->alter('xprojects',  $this->getArrToStrList('set_xprojects'))
        ->alter('owners',     $this->getArrToStrList('set_owners'))
        ->alter('authors',    $this->getArrToStrList('set_authors'))
        ->alter('lpriority', $min_priority)
        ->alter('hpriority', $max_priority)
        ->alter('tasks', $task_ids)
        ->alter('search', $search_text);

      return id(new AphrontRedirectResponse())->setURI($uri);
    }

    $nav = $this->buildBaseSideNav();

    $has_filter = array(
      'action' => true,
      'created' => true,
      'subscribed' => true,
      'triage' => true,
      'projecttriage' => true,
      'projectall' => true,
    );

    $query = null;
    $key = $request->getStr('key');
    if (!$key && !$this->view) {
      if ($this->getDefaultQuery()) {
        $key = $this->getDefaultQuery()->getQueryKey();
      }
    }

    if ($key) {
      $query = id(new PhabricatorSearchQuery())->loadOneWhere(
        'queryKey = %s',
        $key);
    }

    // If the user is running a saved query, load query parameters from that
    // query. Otherwise, build a new query object from the HTTP request.

    if ($query) {
      $nav->selectFilter('Q:'.$query->getQueryKey(), 'custom');
      $this->view = 'custom';
    } else {
      $this->view = $nav->selectFilter($this->view, 'action');
      $query = $this->buildQueryFromRequest();
    }

    // Execute the query.

    list($tasks, $handles, $total_count) = self::loadTasks(
      $query,
      $user);

    // Extract information we need to render the filters from the query.

    $search_text    = $query->getParameter('fullTextSearch');

    $user_phids     = $query->getParameter('userPHIDs', array());
    $task_ids       = $query->getParameter('taskIDs', array());
    $owner_phids    = $query->getParameter('ownerPHIDs', array());
    $author_phids   = $query->getParameter('authorPHIDs', array());
    $project_phids  = $query->getParameter('projectPHIDs', array());
    $any_project_phids = $query->getParameter(
      'anyProjectPHIDs',
      array());
    $exclude_project_phids = $query->getParameter(
      'excludeProjectPHIDs',
      array());
    $low_priority   = $query->getParameter('lowPriority');
    $high_priority  = $query->getParameter('highPriority');

    $page_size = $query->getParameter('limit');
    $page = $query->getParameter('offset');

    $q_status = $query->getParameter('status');
    $q_group  = $query->getParameter('group');
    $q_order  = $query->getParameter('order');

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->setNoShading(true)
      ->setAction(
          $request->getRequestURI()
            ->alter('key', null)
            ->alter(
              $this->getStatusRequestKey(),
              $this->getStatusRequestValue($q_status))
            ->alter(
              $this->getOrderRequestKey(),
              $this->getOrderRequestValue($q_order))
            ->alter(
              $this->getGroupRequestKey(),
              $this->getGroupRequestValue($q_group)));

    if (isset($has_filter[$this->view])) {
      $tokens = array();
      foreach ($user_phids as $phid) {
        $tokens[$phid] = $handles[$phid]->getFullName();
      }
      $form->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setDatasource('/typeahead/common/searchowner/')
          ->setName('set_users')
          ->setLabel(pht('Users'))
          ->setValue($tokens));
    }

    if ($this->view == 'custom') {
      $form->appendChild(
        id(new AphrontFormTextControl())
          ->setName('set_search')
          ->setLabel(pht('Search'))
          ->setValue($search_text));
      $form->appendChild(
        id(new AphrontFormTextControl())
          ->setName('set_tasks')
          ->setLabel(pht('Task IDs'))
          ->setValue(join(',', $task_ids)));

      $tokens = array();
      foreach ($owner_phids as $phid) {
        $tokens[$phid] = $handles[$phid]->getFullName();
      }
      $form->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setDatasource('/typeahead/common/searchowner/')
          ->setName('set_owners')
          ->setLabel(pht('Owners'))
          ->setValue($tokens));

      $tokens = array();
      foreach ($author_phids as $phid) {
        $tokens[$phid] = $handles[$phid]->getFullName();
      }
      $form->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setDatasource('/typeahead/common/users/')
          ->setName('set_authors')
          ->setLabel(pht('Authors'))
          ->setValue($tokens));
    }

    $tokens = array();
    foreach ($project_phids as $phid) {
      $tokens[$phid] = $handles[$phid]->getFullName();
    }
    if ($this->view != 'projectall' && $this->view != 'projecttriage') {

      $caption = null;
      if ($this->view == 'custom') {
        $caption = pht('Find tasks in ALL of these projects ("AND" query).');
      }

      $form->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setDatasource('/typeahead/common/searchproject/')
          ->setName('set_projects')
          ->setLabel(pht('Projects'))
          ->setCaption($caption)
          ->setValue($tokens));
    }

    if ($this->view == 'custom') {
      $atokens = array();
      foreach ($any_project_phids as $phid) {
        $atokens[$phid] = $handles[$phid]->getFullName();
      }
      $form->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setDatasource('/typeahead/common/projects/')
          ->setName('set_aprojects')
          ->setLabel(pht('Any Projects'))
          ->setCaption(pht('Find tasks in ANY of these projects ("OR" query).'))
          ->setValue($atokens));

      $tokens = array();
      foreach ($exclude_project_phids as $phid) {
        $tokens[$phid] = $handles[$phid]->getFullName();
      }
      $form->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setDatasource('/typeahead/common/projects/')
          ->setName('set_xprojects')
          ->setLabel(pht('Exclude Projects'))
          ->setCaption(pht('Find tasks NOT in any of these projects.'))
          ->setValue($tokens));

      $priority = ManiphestTaskPriority::getLowestPriority();
      if ($low_priority !== null) {
        $priority = $low_priority;
      }

      $form->appendChild(
          id(new AphrontFormSelectControl())
            ->setLabel(pht('Min Priority'))
            ->setName('set_lpriority')
            ->setValue($priority)
            ->setOptions(array_reverse(
                ManiphestTaskPriority::getTaskPriorityMap(), true)));

      $priority = ManiphestTaskPriority::getHighestPriority();
      if ($high_priority !== null) {
        $priority = $high_priority;
      }

      $form->appendChild(
          id(new AphrontFormSelectControl())
            ->setLabel(pht('Max Priority'))
            ->setName('set_hpriority')
            ->setValue($priority)
            ->setOptions(ManiphestTaskPriority::getTaskPriorityMap()));

    }

    $form
      ->appendChild($this->renderStatusControl($q_status))
      ->appendChild($this->renderGroupControl($q_group))
      ->appendChild($this->renderOrderControl($q_order));

    $submit = id(new AphrontFormSubmitControl())
      ->setValue(pht('Filter Tasks'));

    // Only show "Save..." for novel queries which have some kind of query
    // parameters set.
    if ($this->view === 'custom'
        && empty($key)
        && $request->getRequestURI()->getQueryParams()) {
      $submit->addCancelButton(
        '/maniphest/custom/edit/?key='.$query->getQueryKey(),
        pht('Save Custom Query...'));
    }

    $form->appendChild($submit);

    $create_uri = new PhutilURI('/maniphest/task/create/');
    if ($project_phids) {
      // If we have project filters selected, use them as defaults for task
      // creation.
      $create_uri->setQueryParam('projects', implode(';', $project_phids));
    }

    $filter = new AphrontListFilterView();
    if (empty($key)) {
      $filter->appendChild($form);
    }

    $have_tasks = false;
    foreach ($tasks as $group => $list) {
      if (count($list)) {
        $have_tasks = true;
        break;
      }
    }

    require_celerity_resource('maniphest-task-summary-css');

    $list_container = new AphrontNullView();
    $list_container->appendChild(hsprintf(
      '<div class="maniphest-list-container">'));

    if (!$have_tasks) {
      $no_tasks = pht('No matching tasks.');
      $list_container->appendChild(hsprintf(
        '<h1 class="maniphest-task-group-header">'.
          '%s'.
        '</h1>',
        $no_tasks));
      $result_count = null;
    } else {
      $pager = new AphrontPagerView();
      $pager->setURI($request->getRequestURI(), 'offset');
      $pager->setPageSize($page_size);
      $pager->setOffset($page);
      $pager->setCount($total_count);

      $cur = ($pager->getOffset() + 1);
      $max = min($pager->getOffset() + $page_size, $total_count);
      $tot = $total_count;

      $results = pht('Displaying tasks %s - %s of %s.',
        number_format($cur),
        number_format($max),
        number_format($tot));
      $result_count = phutil_tag(
        'div',
        array(
          'class' => 'maniphest-total-result-count'
        ),
        $results);

      $selector = new AphrontNullView();

      $group = $query->getParameter('group');
      $order = $query->getParameter('order');
      $is_draggable =
        ($order == 'priority') &&
        ($group == 'none' || $group == 'priority');

      $lists = array();
      foreach ($tasks as $group => $list) {
        $task_list = new ManiphestTaskListView();
        $task_list->setShowBatchControls(true);
        if ($is_draggable) {
          $task_list->setShowSubpriorityControls(true);
        }
        $task_list->setUser($user);
        $task_list->setTasks($list);
        $task_list->setHandles($handles);

        $count = number_format(count($list));

        $header =
          javelin_tag(
            'h1',
            array(
              'class' => 'maniphest-task-group-header',
              'sigil' => 'task-group',
              'meta'  => array(
                'priority' => head($list)->getPriority(),
              ),
            ),
            $group.' ('.$count.')');

        $lists[] =
          phutil_tag(
            'div',
            array(
              'class' => 'maniphest-task-group'
            ),
            array(
              $header,
              $task_list,
            ));
      }

      $selector->appendChild($lists);
      $selector->appendChild($this->renderBatchEditor($query));

      $form_id = celerity_generate_unique_node_id();
      $selector = phabricator_form(
        $user,
        array(
          'method' => 'POST',
          'action' => '/maniphest/batch/',
          'id'     => $form_id,
        ),
        $selector->render());

      $list_container->appendChild($selector);
      $list_container->appendChild($pager);

      Javelin::initBehavior(
        'maniphest-subpriority-editor',
        array(
          'root'  => $form_id,
          'uri'   =>  '/maniphest/subpriority/',
        ));
    }

    $nav->appendChild($filter);
    $nav->appendChild($result_count);
    $nav->appendChild($list_container);

    $title = pht('Task List');

    $crumbs = $this->buildApplicationCrumbs()
      ->addCrumb(
        id(new PhabricatorCrumbView())
          ->setName($title))
      ->addAction(
        id(new PhabricatorMenuItemView())
          ->setHref($this->getApplicationURI('/task/create/'))
          ->setName(pht('Create Task'))
          ->setIcon('create'));

    $nav->setCrumbs($crumbs);

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => $title,
        'device' => true,
        'dust' => true,
      ));
  }

  public static function loadTasks(
    PhabricatorSearchQuery $search_query,
    PhabricatorUser $viewer) {

    $any_project = false;
    $search_text = $search_query->getParameter('fullTextSearch');
    $user_phids = $search_query->getParameter('userPHIDs', array());
    $task_ids = $search_query->getParameter('taskIDs', array());
    $project_phids = $search_query->getParameter('projectPHIDs', array());
    $any_project_phids = $search_query->getParameter(
      'anyProjectPHIDs',
      array());
    $xproject_phids = $search_query->getParameter(
      'excludeProjectPHIDs',
      array());
    $owner_phids = $search_query->getParameter('ownerPHIDs', array());
    $author_phids = $search_query->getParameter('authorPHIDs', array());

    $low_priority = $search_query->getParameter('lowPriority');
    $low_priority = coalesce($low_priority,
        ManiphestTaskPriority::getLowestPriority());
    $high_priority = $search_query->getParameter('highPriority');
    $high_priority = coalesce($high_priority,
      ManiphestTaskPriority::getHighestPriority());

    $query = new ManiphestTaskQuery();
    $query->withTaskIDs($task_ids);

    if ($project_phids) {
      $query->withAllProjects($project_phids);
    }

    if ($xproject_phids) {
      $query->withoutProjects($xproject_phids);
    }

    if ($any_project_phids) {
      $query->withAnyProjects($any_project_phids);
    }

    if ($owner_phids) {
      $query->withOwners($owner_phids);
    }

    if ($author_phids) {
      $query->withAuthors($author_phids);
    }

    $status = $search_query->getParameter('status', 'all');
    if (!empty($status['open']) && !empty($status['closed'])) {
      $query->withStatus(ManiphestTaskQuery::STATUS_ANY);
    } else if (!empty($status['open'])) {
      $query->withStatus(ManiphestTaskQuery::STATUS_OPEN);
    } else {
      $query->withStatus(ManiphestTaskQuery::STATUS_CLOSED);
    }

    switch ($search_query->getParameter('view')) {
      case 'action':
        $query->withOwners($user_phids);
        break;
      case 'created':
        $query->withAuthors($user_phids);
        break;
      case 'subscribed':
        $query->withSubscribers($user_phids);
        break;
      case 'triage':
        $query->withOwners($user_phids);
        $query->withPriority(ManiphestTaskPriority::PRIORITY_TRIAGE);
        break;
      case 'alltriage':
        $query->withPriority(ManiphestTaskPriority::PRIORITY_TRIAGE);
        break;
      case 'all':
        break;
      case 'projecttriage':
        $query->withPriority(ManiphestTaskPriority::PRIORITY_TRIAGE);
        break;
      case 'projectall':
        break;
      case 'custom':
        $query->withPrioritiesBetween($low_priority, $high_priority);
        break;
    }

    $query->withFullTextSearch($search_text);

    $order_map = array(
      'priority'  => ManiphestTaskQuery::ORDER_PRIORITY,
      'created'   => ManiphestTaskQuery::ORDER_CREATED,
      'title'     => ManiphestTaskQuery::ORDER_TITLE,
    );
    $query->setOrderBy(
      idx(
        $order_map,
        $search_query->getParameter('order'),
        ManiphestTaskQuery::ORDER_MODIFIED));

    $group_map = array(
      'priority'  => ManiphestTaskQuery::GROUP_PRIORITY,
      'owner'     => ManiphestTaskQuery::GROUP_OWNER,
      'status'    => ManiphestTaskQuery::GROUP_STATUS,
      'project'   => ManiphestTaskQuery::GROUP_PROJECT,
    );
    $query->setGroupBy(
      idx(
        $group_map,
        $search_query->getParameter('group'),
        ManiphestTaskQuery::GROUP_NONE));

    $query->setCalculateRows(true);
    $query->setLimit($search_query->getParameter('limit'));
    $query->setOffset($search_query->getParameter('offset'));

    $data = $query->execute();
    $total_row_count = $query->getRowCount();

    $project_group_phids = array();
    if ($search_query->getParameter('group') == 'project') {
      foreach ($data as $task) {
        foreach ($task->getProjectPHIDs() as $phid) {
          $project_group_phids[] = $phid;
        }
      }
    }

    $handle_phids = mpull($data, 'getOwnerPHID');
    $handle_phids = array_merge(
      $handle_phids,
      $project_phids,
      $user_phids,
      $xproject_phids,
      $owner_phids,
      $author_phids,
      $project_group_phids,
      $any_project_phids,
      array_mergev(mpull($data, 'getProjectPHIDs')));
    $handles = id(new PhabricatorObjectHandleData($handle_phids))
      ->setViewer($viewer)
      ->loadHandles();

    switch ($search_query->getParameter('group')) {
      case 'priority':
        $data = mgroup($data, 'getPriority');

        // If we have invalid priorities, they'll all map to "???". Merge
        // arrays to prevent them from overwriting each other.

        $out = array();
        foreach ($data as $pri => $tasks) {
          $out[ManiphestTaskPriority::getTaskPriorityName($pri)][] = $tasks;
        }
        foreach ($out as $pri => $tasks) {
          $out[$pri] = array_mergev($tasks);
        }
        $data = $out;

        break;
      case 'status':
        $data = mgroup($data, 'getStatus');

        $out = array();
        foreach ($data as $status => $tasks) {
          $out[ManiphestTaskStatus::getTaskStatusFullName($status)] = $tasks;
        }

        $data = $out;
        break;
      case 'owner':
        $data = mgroup($data, 'getOwnerPHID');

        $out = array();
        foreach ($data as $phid => $tasks) {
          if ($phid) {
            $out[$handles[$phid]->getFullName()] = $tasks;
          } else {
            $out['Unassigned'] = $tasks;
          }
        }

        $data = $out;
        ksort($data);

        // Move "Unassigned" to the top of the list.
        if (isset($data['Unassigned'])) {
          $data = array('Unassigned' => $out['Unassigned']) + $out;
        }
        break;
      case 'project':
        $grouped = array();
        foreach ($query->getGroupByProjectResults() as $project => $tasks) {
          foreach ($tasks as $task) {
            $group = $project ? $handles[$project]->getName() : 'No Project';
            $grouped[$group][$task->getID()] = $task;
          }
        }
        $data = $grouped;
        ksort($data);

        // Move "No Project" to the end of the list.
        if (isset($data['No Project'])) {
          $noproject = $data['No Project'];
          unset($data['No Project']);
          $data += array('No Project' => $noproject);
        }
        break;
      default:
        $data = array(
          'Tasks' => $data,
        );
        break;
    }

    return array($data, $handles, $total_row_count);
  }

  private function renderBatchEditor(PhabricatorSearchQuery $search_query) {
    Javelin::initBehavior(
      'maniphest-batch-selector',
      array(
        'selectAll'   => 'batch-select-all',
        'selectNone'  => 'batch-select-none',
        'submit'      => 'batch-select-submit',
        'status'      => 'batch-select-status-cell',
      ));

    $select_all = javelin_tag(
      'a',
      array(
        'href'        => '#',
        'mustcapture' => true,
        'class'       => 'grey button',
        'id'          => 'batch-select-all',
      ),
      pht('Select All'));

    $select_none = javelin_tag(
      'a',
      array(
        'href'        => '#',
        'mustcapture' => true,
        'class'       => 'grey button',
        'id'          => 'batch-select-none',
      ),
      pht('Clear Selection'));

    $submit = phutil_tag(
      'button',
      array(
        'id'          => 'batch-select-submit',
        'disabled'    => 'disabled',
        'class'       => 'disabled',
      ),
      pht("Batch Edit Selected \xC2\xBB"));

    $export = javelin_tag(
      'a',
      array(
        'href' => '/maniphest/export/'.$search_query->getQueryKey().'/',
        'class' => 'grey button',
      ),
      pht('Export to Excel'));

    return hsprintf(
      '<div class="maniphest-batch-editor">'.
        '<div class="batch-editor-header">%s</div>'.
        '<table class="maniphest-batch-editor-layout">'.
          '<tr>'.
            '<td>%s%s</td>'.
            '<td>%s</td>'.
            '<td id="batch-select-status-cell">%s</td>'.
            '<td class="batch-select-submit-cell">%s</td>'.
          '</tr>'.
        '</table>'.
      '</table>',
      pht('Batch Task Editor'),
      $select_all,
      $select_none,
      $export,
      pht('0 Selected'),
      $submit);
  }

  private function buildQueryFromRequest() {
    $request  = $this->getRequest();
    $user     = $request->getUser();

    $status   = $this->getStatusValueFromRequest();
    $group    = $this->getGroupValueFromRequest();
    $order    = $this->getOrderValueFromRequest();

    $user_phids = $request->getStrList(
      'users',
      array($user->getPHID()));

    if ($this->view == 'projecttriage' || $this->view == 'projectall') {
      $projects = id(new PhabricatorProjectQuery())
        ->setViewer($user)
        ->withMemberPHIDs($user_phids)
        ->execute();
      $any_project_phids = mpull($projects, 'getPHID');
    } else {
      $any_project_phids = $request->getStrList('aprojects');
    }

    $project_phids = $request->getStrList('projects');
    $exclude_project_phids = $request->getStrList('xprojects');
    $task_ids = $request->getStrList('tasks');

    if ($task_ids) {
      // We only need the integer portion of each task ID, so get rid of any
      // non-numeric elements
      $numeric_task_ids = array();

      foreach ($task_ids as $task_id) {
        $task_id = preg_replace('/\D+/', '', $task_id);
        if (!empty($task_id)) {
          $numeric_task_ids[] = $task_id;
        }
      }

      if (empty($numeric_task_ids)) {
        $numeric_task_ids = array(null);
      }

      $task_ids = $numeric_task_ids;
    }

    $owner_phids    = $request->getStrList('owners');
    $author_phids   = $request->getStrList('authors');

    $search_string  = $request->getStr('search');

    $low_priority   = $request->getInt('lpriority');
    $high_priority  = $request->getInt('hpriority');

    $page = $request->getInt('offset');
    $page_size = self::DEFAULT_PAGE_SIZE;

    $query = new PhabricatorSearchQuery();
    $query->setQuery('<<maniphest>>');
    $query->setParameters(
      array(
        'fullTextSearch'      => $search_string,
        'view'                => $this->view,
        'userPHIDs'           => $user_phids,
        'projectPHIDs'        => $project_phids,
        'anyProjectPHIDs'     => $any_project_phids,
        'excludeProjectPHIDs' => $exclude_project_phids,
        'ownerPHIDs'          => $owner_phids,
        'authorPHIDs'         => $author_phids,
        'taskIDs'             => $task_ids,
        'lowPriority'         => $low_priority,
        'highPriority'        => $high_priority,
        'group'               => $group,
        'order'               => $order,
        'offset'              => $page,
        'limit'               => $page_size,
        'status'              => $status,
      ));

    $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
    $query->save();
    unset($unguarded);

    return $query;
  }

/* -(  Toggle Button Controls  )---------------------------------------------

  These are a giant mess since we have several different values: the request
  key (GET param used in requests), the request value (short names used in
  requests to keep URIs readable), and the query value (complex value stored in
  the query).

*/

  private function getStatusValueFromRequest() {
    $map = $this->getStatusMap();
    $val = $this->getRequest()->getStr($this->getStatusRequestKey());
    return idx($map, $val, head($map));
  }

  private function getGroupValueFromRequest() {
    $map = $this->getGroupMap();
    $val = $this->getRequest()->getStr($this->getGroupRequestKey());
    return idx($map, $val, head($map));
  }

  private function getOrderValueFromRequest() {
    $map = $this->getOrderMap();
    $val = $this->getRequest()->getStr($this->getOrderRequestKey());
    return idx($map, $val, head($map));
  }

  private function getStatusRequestKey() {
    return 's';
  }

  private function getGroupRequestKey() {
    return 'g';
  }

  private function getOrderRequestKey() {
    return 'o';
  }

  private function getStatusRequestValue($value) {
    return array_search($value, $this->getStatusMap());
  }

  private function getGroupRequestValue($value) {
    return array_search($value, $this->getGroupMap());
  }

  private function getOrderRequestValue($value) {
    return array_search($value, $this->getOrderMap());
  }

  private function getStatusMap() {
    return array(
      'o'   => array(
        'open' => true,
      ),
      'c'   => array(
        'closed' => true,
      ),
      'oc'  => array(
        'open' => true,
        'closed' => true,
      ),
    );
  }

  private function getGroupMap() {
    return array(
      'p' => 'priority',
      'o' => 'owner',
      's' => 'status',
      'j' => 'project',
      'n' => 'none',
    );
  }

  private function getOrderMap() {
    return array(
      'p' => 'priority',
      'u' => 'updated',
      'c' => 'created',
      't' => 'title',
    );
  }

  private function getStatusButtonMap() {
    return array(
      'o'   => pht('Open'),
      'c'   => pht('Closed'),
      'oc'  => pht('All'),
    );
  }

  private function getGroupButtonMap() {
    return array(
      'p' => pht('Priority'),
      'o' => pht('Owner'),
      's' => pht('Status'),
      'j' => pht('Project'),
      'n' => pht('None'),
    );
  }

  private function getOrderButtonMap() {
    return array(
      'p' => pht('Priority'),
      'u' => pht('Updated'),
      'c' => pht('Created'),
      't' => pht('Title'),
    );
  }

  public function renderStatusControl($value) {
    $request = $this->getRequest();
    return id(new AphrontFormToggleButtonsControl())
      ->setLabel(pht('Status'))
      ->setValue($this->getStatusRequestValue($value))
      ->setBaseURI($request->getRequestURI(), $this->getStatusRequestKey())
      ->setButtons($this->getStatusButtonMap());
  }

  public function renderOrderControl($value) {
    $request = $this->getRequest();
    return id(new AphrontFormToggleButtonsControl())
      ->setLabel(pht('Order'))
      ->setValue($this->getOrderRequestValue($value))
      ->setBaseURI($request->getRequestURI(), $this->getOrderRequestKey())
      ->setButtons($this->getOrderButtonMap());
  }

  public function renderGroupControl($value) {
    $request = $this->getRequest();
    return id(new AphrontFormToggleButtonsControl())
      ->setLabel(pht('Group'))
      ->setValue($this->getGroupRequestValue($value))
      ->setBaseURI($request->getRequestURI(), $this->getGroupRequestKey())
      ->setButtons($this->getGroupButtonMap());
  }

}
