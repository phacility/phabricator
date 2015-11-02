<?php

final class PhabricatorProjectBoardViewController
  extends PhabricatorProjectBoardController {

  const BATCH_EDIT_ALL = 'all';

  private $id;
  private $slug;
  private $handles;
  private $queryKey;
  private $filter;
  private $sortKey;
  private $showHidden;

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getUser();
    $id = $request->getURIData('id');

    $show_hidden = $request->getBool('hidden');
    $this->showHidden = $show_hidden;

    $project = id(new PhabricatorProjectQuery())
      ->setViewer($viewer)
      ->needImages(true);
    $id = $request->getURIData('id');
    $slug = $request->getURIData('slug');
    if ($slug) {
      $project->withSlugs(array($slug));
    } else {
      $project->withIDs(array($id));
    }
    $project = $project->executeOne();
    if (!$project) {
      return new Aphront404Response();
    }

    $this->setProject($project);
    $this->id = $project->getID();

    $sort_key = $request->getStr('order');
    switch ($sort_key) {
      case PhabricatorProjectColumn::ORDER_NATURAL:
      case PhabricatorProjectColumn::ORDER_PRIORITY:
        break;
      default:
        $sort_key = PhabricatorProjectColumn::DEFAULT_ORDER;
        break;
    }
    $this->sortKey = $sort_key;

    $column_query = id(new PhabricatorProjectColumnQuery())
      ->setViewer($viewer)
      ->withProjectPHIDs(array($project->getPHID()));
    if (!$show_hidden) {
      $column_query->withStatuses(
        array(PhabricatorProjectColumn::STATUS_ACTIVE));
    }

    $columns = $column_query->execute();
    $columns = mpull($columns, null, 'getSequence');

    // TODO: Expand the checks here if we add the ability
    // to hide the Backlog column
    if (!$columns) {
      $can_edit = PhabricatorPolicyFilter::hasCapability(
        $viewer,
        $project,
        PhabricatorPolicyCapability::CAN_EDIT);
      if (!$can_edit) {
        return $this->noAccessDialog($project);
      }
      switch ($request->getStr('initialize-type')) {
        case 'backlog-only':
          $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
            $column = PhabricatorProjectColumn::initializeNewColumn($viewer)
              ->setSequence(0)
              ->setProperty('isDefault', true)
              ->setProjectPHID($project->getPHID())
              ->save();
            $column->attachProject($project);
            $columns[0] = $column;
          unset($unguarded);
          break;
        case 'import':
          return id(new AphrontRedirectResponse())
            ->setURI(
              $this->getApplicationURI('board/'.$project->getID().'/import/'));
          break;
        default:
          return $this->initializeWorkboardDialog($project);
          break;
      }
    }

    ksort($columns);

    $board_uri = $this->getApplicationURI('board/'.$project->getID().'/');

    $engine = id(new ManiphestTaskSearchEngine())
      ->setViewer($viewer)
      ->setBaseURI($board_uri)
      ->setIsBoardView(true);

    if ($request->isFormPost()) {
      $saved = $engine->buildSavedQueryFromRequest($request);
      $engine->saveQuery($saved);
      $filter_form = id(new AphrontFormView())
        ->setUser($viewer);
      $engine->buildSearchForm($filter_form, $saved);
      if ($engine->getErrors()) {
        return $this->newDialog()
          ->setWidth(AphrontDialogView::WIDTH_FULL)
          ->setTitle(pht('Advanced Filter'))
          ->appendChild($filter_form->buildLayoutView())
          ->setErrors($engine->getErrors())
          ->setSubmitURI($board_uri)
          ->addSubmitButton(pht('Apply Filter'))
          ->addCancelButton($board_uri);
      }
      return id(new AphrontRedirectResponse())->setURI(
        $this->getURIWithState(
          $engine->getQueryResultsPageURI($saved->getQueryKey())));
    }

    $query_key = $request->getURIData('queryKey');
    if (!$query_key) {
      $query_key = 'open';
    }
    $this->queryKey = $query_key;

    $custom_query = null;
    if ($engine->isBuiltinQuery($query_key)) {
      $saved = $engine->buildSavedQueryFromBuiltin($query_key);
    } else {
      $saved = id(new PhabricatorSavedQueryQuery())
        ->setViewer($viewer)
        ->withQueryKeys(array($query_key))
        ->executeOne();

      if (!$saved) {
        return new Aphront404Response();
      }

      $custom_query = $saved;
    }

    if ($request->getURIData('filter')) {
      $filter_form = id(new AphrontFormView())
        ->setUser($viewer);
      $engine->buildSearchForm($filter_form, $saved);

      return $this->newDialog()
        ->setWidth(AphrontDialogView::WIDTH_FULL)
        ->setTitle(pht('Advanced Filter'))
        ->appendChild($filter_form->buildLayoutView())
        ->setSubmitURI($board_uri)
        ->addSubmitButton(pht('Apply Filter'))
        ->addCancelButton($board_uri);
    }

    $task_query = $engine->buildQueryFromSavedQuery($saved);

    $tasks = $task_query
      ->withEdgeLogicPHIDs(
        PhabricatorProjectObjectHasProjectEdgeType::EDGECONST,
        PhabricatorQueryConstraint::OPERATOR_AND,
        array($project->getPHID()))
      ->setOrder(ManiphestTaskQuery::ORDER_PRIORITY)
      ->setViewer($viewer)
      ->execute();
    $tasks = mpull($tasks, null, 'getPHID');

    if ($tasks) {
      $positions = id(new PhabricatorProjectColumnPositionQuery())
        ->setViewer($viewer)
        ->withObjectPHIDs(mpull($tasks, 'getPHID'))
        ->withColumns($columns)
        ->execute();
      $positions = mpull($positions, null, 'getObjectPHID');
    } else {
      $positions = array();
    }

    $task_map = array();
    foreach ($tasks as $task) {
      $task_phid = $task->getPHID();
      if (empty($positions[$task_phid])) {
        // This shouldn't normally be possible because we create positions on
        // demand, but we might have raced as an object was removed from the
        // board. Just drop the task if we don't have a position for it.
        continue;
      }

      $position = $positions[$task_phid];
      $task_map[$position->getColumnPHID()][] = $task_phid;
    }

    // If we're showing the board in "natural" order, sort columns by their
    // column positions.
    if ($this->sortKey == PhabricatorProjectColumn::ORDER_NATURAL) {
      foreach ($task_map as $column_phid => $task_phids) {
        $order = array();
        foreach ($task_phids as $task_phid) {
          if (isset($positions[$task_phid])) {
            $order[$task_phid] = $positions[$task_phid]->getOrderingKey();
          } else {
            $order[$task_phid] = 0;
          }
        }
        asort($order);
        $task_map[$column_phid] = array_keys($order);
      }
    }

    $task_can_edit_map = id(new PhabricatorPolicyFilter())
      ->setViewer($viewer)
      ->requireCapabilities(array(PhabricatorPolicyCapability::CAN_EDIT))
      ->apply($tasks);

    // If this is a batch edit, select the editable tasks in the chosen column
    // and ship the user into the batch editor.
    $batch_edit = $request->getStr('batch');
    if ($batch_edit) {
      if ($batch_edit !== self::BATCH_EDIT_ALL) {
        $column_id_map = mpull($columns, null, 'getID');
        $batch_column = idx($column_id_map, $batch_edit);
        if (!$batch_column) {
          return new Aphront404Response();
        }

        $batch_task_phids = idx($task_map, $batch_column->getPHID(), array());
        foreach ($batch_task_phids as $key => $batch_task_phid) {
          if (empty($task_can_edit_map[$batch_task_phid])) {
            unset($batch_task_phids[$key]);
          }
        }

        $batch_tasks = array_select_keys($tasks, $batch_task_phids);
      } else {
        $batch_tasks = $task_can_edit_map;
      }

      if (!$batch_tasks) {
        $cancel_uri = $this->getURIWithState($board_uri);
        return $this->newDialog()
          ->setTitle(pht('No Editable Tasks'))
          ->appendParagraph(
            pht(
              'The selected column contains no visible tasks which you '.
              'have permission to edit.'))
          ->addCancelButton($board_uri);
      }

      $batch_ids = mpull($batch_tasks, 'getID');
      $batch_ids = implode(',', $batch_ids);

      $batch_uri = new PhutilURI('/maniphest/batch/');
      $batch_uri->setQueryParam('board', $this->id);
      $batch_uri->setQueryParam('batch', $batch_ids);
      return id(new AphrontRedirectResponse())
        ->setURI($batch_uri);
    }

    $board_id = celerity_generate_unique_node_id();

    $board = id(new PHUIWorkboardView())
      ->setUser($viewer)
      ->setID($board_id);

    $behavior_config = array(
      'boardID' => $board_id,
      'projectPHID' => $project->getPHID(),
      'moveURI' => $this->getApplicationURI('move/'.$project->getID().'/'),
      'createURI' => '/maniphest/task/create/',
      'order' => $this->sortKey,
    );
    $this->initBehavior(
      'project-boards',
      $behavior_config);
    $this->addExtraQuickSandConfig(array('boardConfig' => $behavior_config));

    $this->handles = ManiphestTaskListView::loadTaskHandles($viewer, $tasks);

    foreach ($columns as $column) {
      $task_phids = idx($task_map, $column->getPHID(), array());
      $column_tasks = array_select_keys($tasks, $task_phids);

      $panel = id(new PHUIWorkpanelView())
        ->setHeader($column->getDisplayName())
        ->setSubHeader($column->getDisplayType())
        ->addSigil('workpanel');

      $header_icon = $column->getHeaderIcon();
      if ($header_icon) {
        $panel->setHeaderIcon($header_icon);
      }

      if ($column->isHidden()) {
        $panel->addClass('project-panel-hidden');
      }

      $column_menu = $this->buildColumnMenu($project, $column);
      $panel->addHeaderAction($column_menu);

      $tag_id = celerity_generate_unique_node_id();
      $tag_content_id = celerity_generate_unique_node_id();

      $count_tag = id(new PHUITagView())
        ->setType(PHUITagView::TYPE_SHADE)
        ->setShade(PHUITagView::COLOR_BLUE)
        ->setID($tag_id)
        ->setName(phutil_tag('span', array('id' => $tag_content_id), '-'))
        ->setStyle('display: none');

      $panel->setHeaderTag($count_tag);

      $cards = id(new PHUIObjectItemListView())
        ->setUser($viewer)
        ->setFlush(true)
        ->setAllowEmptyList(true)
        ->addSigil('project-column')
        ->setMetadata(
          array(
            'columnPHID' => $column->getPHID(),
            'countTagID' => $tag_id,
            'countTagContentID' => $tag_content_id,
            'pointLimit' => $column->getPointLimit(),
          ));

      foreach ($column_tasks as $task) {
        $owner = null;
        if ($task->getOwnerPHID()) {
          $owner = $this->handles[$task->getOwnerPHID()];
        }
        $can_edit = idx($task_can_edit_map, $task->getPHID(), false);
        $cards->addItem(id(new ProjectBoardTaskCard())
          ->setViewer($viewer)
          ->setTask($task)
          ->setOwner($owner)
          ->setCanEdit($can_edit)
          ->getItem());
      }
      $panel->setCards($cards);
      $board->addPanel($panel);
    }

    $sort_menu = $this->buildSortMenu(
      $viewer,
      $sort_key);

    $filter_menu = $this->buildFilterMenu(
      $viewer,
      $custom_query,
      $engine,
      $query_key);

    $manage_menu = $this->buildManageMenu($project, $show_hidden);

    $header_link = phutil_tag(
      'a',
      array(
        'href' => $this->getApplicationURI('profile/'.$project->getID().'/'),
      ),
      $project->getName());

    $header = id(new PHUIHeaderView())
      ->setHeader($header_link)
      ->setUser($viewer)
      ->setNoBackground(true)
      ->addActionLink($sort_menu)
      ->addActionLink($filter_menu)
      ->addActionLink($manage_menu)
      ->setPolicyObject($project);

    $header_box = id(new PHUIBoxView())
      ->appendChild($header)
      ->addClass('project-board-header');

    $board_box = id(new PHUIBoxView())
      ->appendChild($board)
      ->addClass('project-board-wrapper');

    $nav = $this->buildIconNavView($project);
    $nav->appendChild($header_box);
    $nav->appendChild($board_box);

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => pht('%s Board', $project->getName()),
        'showFooter' => false,
        'pageObjects' => array($project->getPHID()),
      ));
  }

  private function buildSortMenu(
    PhabricatorUser $viewer,
    $sort_key) {

    $sort_icon = id(new PHUIIconView())
      ->setIconFont('fa-sort-amount-asc bluegrey');

    $named = array(
      PhabricatorProjectColumn::ORDER_NATURAL => pht('Natural'),
      PhabricatorProjectColumn::ORDER_PRIORITY => pht('Sort by Priority'),
    );

    $base_uri = $this->getURIWithState();

    $items = array();
    foreach ($named as $key => $name) {
      $is_selected = ($key == $sort_key);
      if ($is_selected) {
        $active_order = $name;
      }

      $item = id(new PhabricatorActionView())
        ->setIcon('fa-sort-amount-asc')
        ->setSelected($is_selected)
        ->setName($name);

      $uri = $base_uri->alter('order', $key);
      $item->setHref($uri);

      $items[] = $item;
    }

    $sort_menu = id(new PhabricatorActionListView())
      ->setUser($viewer);
    foreach ($items as $item) {
      $sort_menu->addAction($item);
    }

    $sort_button = id(new PHUIButtonView())
      ->setText(pht('Sort: %s', $active_order))
      ->setIcon($sort_icon)
      ->setTag('a')
      ->setHref('#')
      ->addSigil('boards-dropdown-menu')
      ->setMetadata(
        array(
          'items' => hsprintf('%s', $sort_menu),
        ));

    return $sort_button;
  }
  private function buildFilterMenu(
    PhabricatorUser $viewer,
    $custom_query,
    PhabricatorApplicationSearchEngine $engine,
    $query_key) {

    $filter_icon = id(new PHUIIconView())
      ->setIconFont('fa-search-plus bluegrey');

    $named = array(
      'open' => pht('Open Tasks'),
      'all' => pht('All Tasks'),
    );

    if ($viewer->isLoggedIn()) {
      $named['assigned'] = pht('Assigned to Me');
    }

    if ($custom_query) {
      $named[$custom_query->getQueryKey()] = pht('Custom Filter');
    }

    $items = array();
    foreach ($named as $key => $name) {
      $is_selected = ($key == $query_key);
      if ($is_selected) {
        $active_filter = $name;
      }

      $is_custom = false;
      if ($custom_query) {
        $is_custom = ($key == $custom_query->getQueryKey());
      }

      $item = id(new PhabricatorActionView())
        ->setIcon('fa-search')
        ->setSelected($is_selected)
        ->setName($name);

      if ($is_custom) {
        $uri = $this->getApplicationURI(
          'board/'.$this->id.'/filter/query/'.$key.'/');
        $item->setWorkflow(true);
      } else {
        $uri = $engine->getQueryResultsPageURI($key);
      }

      $uri = $this->getURIWithState($uri);
      $item->setHref($uri);

      $items[] = $item;
    }

    $items[] = id(new PhabricatorActionView())
      ->setIcon('fa-cog')
      ->setHref($this->getApplicationURI('board/'.$this->id.'/filter/'))
      ->setWorkflow(true)
      ->setName(pht('Advanced Filter...'));

    $filter_menu = id(new PhabricatorActionListView())
        ->setUser($viewer);
    foreach ($items as $item) {
      $filter_menu->addAction($item);
    }

    $filter_button = id(new PHUIButtonView())
      ->setText(pht('Filter: %s', $active_filter))
      ->setIcon($filter_icon)
      ->setTag('a')
      ->setHref('#')
      ->addSigil('boards-dropdown-menu')
      ->setMetadata(
        array(
          'items' => hsprintf('%s', $filter_menu),
        ));

    return $filter_button;
  }

  private function buildManageMenu(
    PhabricatorProject $project,
    $show_hidden) {

    $request = $this->getRequest();
    $viewer = $request->getUser();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $project,
      PhabricatorPolicyCapability::CAN_EDIT);

    $manage_icon = id(new PHUIIconView())
      ->setIconFont('fa-cog bluegrey');

    $manage_items = array();

    $manage_items[] = id(new PhabricatorActionView())
      ->setIcon('fa-plus')
      ->setName(pht('Add Column'))
      ->setHref($this->getApplicationURI('board/'.$this->id.'/edit/'))
      ->setDisabled(!$can_edit)
      ->setWorkflow(!$can_edit);

    $manage_items[] = id(new PhabricatorActionView())
      ->setIcon('fa-exchange')
      ->setName(pht('Reorder Columns'))
      ->setHref($this->getApplicationURI('board/'.$this->id.'/reorder/'))
      ->setDisabled(!$can_edit)
      ->setWorkflow(true);

    if ($show_hidden) {
      $hidden_uri = $this->getURIWithState()
        ->setQueryParam('hidden', null);
      $hidden_icon = 'fa-eye-slash';
      $hidden_text = pht('Hide Hidden Columns');
    } else {
      $hidden_uri = $this->getURIWithState()
        ->setQueryParam('hidden', 'true');
      $hidden_icon = 'fa-eye';
      $hidden_text = pht('Show Hidden Columns');
    }

    $manage_items[] = id(new PhabricatorActionView())
      ->setIcon($hidden_icon)
      ->setName($hidden_text)
      ->setHref($hidden_uri);

    $batch_edit_uri = $request->getRequestURI();
    $batch_edit_uri->setQueryParam('batch', self::BATCH_EDIT_ALL);
    $can_batch_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      PhabricatorApplication::getByClass('PhabricatorManiphestApplication'),
      ManiphestBulkEditCapability::CAPABILITY);

    $manage_items[] = id(new PhabricatorActionView())
      ->setIcon('fa-list-ul')
      ->setName(pht('Batch Edit Visible Tasks...'))
      ->setHref($batch_edit_uri)
      ->setDisabled(!$can_batch_edit);

    $manage_menu = id(new PhabricatorActionListView())
        ->setUser($viewer);
    foreach ($manage_items as $item) {
      $manage_menu->addAction($item);
    }

    $manage_button = id(new PHUIButtonView())
      ->setText(pht('Manage Board'))
      ->setIcon($manage_icon)
      ->setTag('a')
      ->setHref('#')
      ->addSigil('boards-dropdown-menu')
      ->setMetadata(
        array(
          'items' => hsprintf('%s', $manage_menu),
        ));

    return $manage_button;
  }

  private function buildColumnMenu(
    PhabricatorProject $project,
    PhabricatorProjectColumn $column) {

    $request = $this->getRequest();
    $viewer = $request->getUser();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $project,
      PhabricatorPolicyCapability::CAN_EDIT);

    $column_items = array();

    $column_items[] = id(new PhabricatorActionView())
      ->setIcon('fa-plus')
      ->setName(pht('Create Task...'))
      ->setHref('/maniphest/task/create/')
      ->addSigil('column-add-task')
      ->setMetadata(
        array(
          'columnPHID' => $column->getPHID(),
        ));

    $batch_edit_uri = $request->getRequestURI();
    $batch_edit_uri->setQueryParam('batch', $column->getID());
    $can_batch_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      PhabricatorApplication::getByClass('PhabricatorManiphestApplication'),
      ManiphestBulkEditCapability::CAPABILITY);

    $column_items[] = id(new PhabricatorActionView())
      ->setIcon('fa-list-ul')
      ->setName(pht('Batch Edit Tasks...'))
      ->setHref($batch_edit_uri)
      ->setDisabled(!$can_batch_edit);

    $detail_uri = $this->getApplicationURI(
      'board/'.$this->id.'/column/'.$column->getID().'/');

    $column_items[] = id(new PhabricatorActionView())
      ->setIcon('fa-columns')
      ->setName(pht('Column Details'))
      ->setHref($detail_uri);

    $can_hide = ($can_edit && !$column->isDefaultColumn());
    $hide_uri = 'board/'.$this->id.'/hide/'.$column->getID().'/';
    $hide_uri = $this->getApplicationURI($hide_uri);
    $hide_uri = $this->getURIWithState($hide_uri);

    if (!$column->isHidden()) {
      $column_items[] = id(new PhabricatorActionView())
        ->setName(pht('Hide Column'))
        ->setIcon('fa-eye-slash')
        ->setHref($hide_uri)
        ->setDisabled(!$can_hide)
        ->setWorkflow(true);
    } else {
      $column_items[] = id(new PhabricatorActionView())
        ->setName(pht('Show Column'))
        ->setIcon('fa-eye')
        ->setHref($hide_uri)
        ->setDisabled(!$can_hide)
        ->setWorkflow(true);
    }

    $column_menu = id(new PhabricatorActionListView())
      ->setUser($viewer);
    foreach ($column_items as $item) {
      $column_menu->addAction($item);
    }

    $column_button = id(new PHUIIconView())
      ->setIconFont('fa-caret-down')
      ->setHref('#')
      ->addSigil('boards-dropdown-menu')
      ->setMetadata(
        array(
          'items' => hsprintf('%s', $column_menu),
        ));

    return $column_button;
  }

  private function initializeWorkboardDialog(PhabricatorProject $project) {

    $instructions = pht('This workboard has not been setup yet.');
    $new_selector = id(new AphrontFormRadioButtonControl())
      ->setName('initialize-type')
      ->setValue('backlog-only')
      ->addButton(
        'backlog-only',
        pht('New Empty Board'),
        pht('Create a new board with just a backlog column.'))
      ->addButton(
        'import',
        pht('Import Columns'),
        pht('Import board columns from another project.'));

    $dialog = id(new AphrontDialogView())
      ->setUser($this->getRequest()->getUser())
      ->setTitle(pht('New Workboard'))
      ->addSubmitButton('Continue')
      ->addCancelButton($this->getApplicationURI('view/'.$project->getID().'/'))
      ->appendParagraph($instructions)
      ->appendChild($new_selector);

    return id(new AphrontDialogResponse())
      ->setDialog($dialog);
  }

  private function noAccessDialog(PhabricatorProject $project) {

    $instructions = pht('This workboard has not been setup yet.');

    $dialog = id(new AphrontDialogView())
      ->setUser($this->getRequest()->getUser())
      ->setTitle(pht('No Workboard'))
      ->addCancelButton($this->getApplicationURI('view/'.$project->getID().'/'))
      ->appendParagraph($instructions);

    return id(new AphrontDialogResponse())
      ->setDialog($dialog);
  }


  /**
   * Add current state parameters (like order and the visibility of hidden
   * columns) to a URI.
   *
   * This allows actions which toggle or adjust one piece of state to keep
   * the rest of the board state persistent. If no URI is provided, this method
   * starts with the request URI.
   *
   * @param string|null   URI to add state parameters to.
   * @return PhutilURI    URI with state parameters.
   */
  private function getURIWithState($base = null) {
    if ($base === null) {
      $base = $this->getRequest()->getRequestURI();
    }

    $base = new PhutilURI($base);

    if ($this->sortKey != PhabricatorProjectColumn::DEFAULT_ORDER) {
      $base->setQueryParam('order', $this->sortKey);
    } else {
      $base->setQueryParam('order', null);
    }

    $base->setQueryParam('hidden', $this->showHidden ? 'true' : null);

    return $base;
  }

}
