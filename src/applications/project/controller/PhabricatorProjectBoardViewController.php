<?php

final class PhabricatorProjectBoardViewController
  extends PhabricatorProjectBoardController {

  private $id;
  private $handles;
  private $queryKey;
  private $filter;

  public function shouldAllowPublic() {
    return true;
  }

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
    $this->queryKey = idx($data, 'queryKey');
    $this->filter = (bool)idx($data, 'filter');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $project = id(new PhabricatorProjectQuery())
      ->setViewer($viewer)
      ->needImages(true)
      ->withIDs(array($this->id))
      ->executeOne();
    if (!$project) {
      return new Aphront404Response();
    }
    $this->setProject($project);

    $columns = id(new PhabricatorProjectColumnQuery())
      ->setViewer($viewer)
      ->withProjectPHIDs(array($project->getPHID()))
      ->withStatuses(array(PhabricatorProjectColumn::STATUS_ACTIVE))
      ->execute();

    $columns = mpull($columns, null, 'getSequence');

    // If there's no default column, create one now.
    if (empty($columns[0])) {
      $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
        $column = PhabricatorProjectColumn::initializeNewColumn($viewer)
          ->setSequence(0)
          ->setProjectPHID($project->getPHID())
          ->save();
        $column->attachProject($project);
        $columns[0] = $column;
      unset($unguarded);
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
      return id(new AphrontRedirectResponse())->setURI(
        $engine->getQueryResultsPageURI($saved->getQueryKey()));
    }

    $query_key = $this->queryKey;
    if (!$query_key) {
      $query_key = 'open';
    }

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

    if ($this->filter) {
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
      ->addWithAllProjects(array($project->getPHID()))
      ->setOrderBy(ManiphestTaskQuery::ORDER_PRIORITY)
      ->setViewer($viewer)
      ->execute();

    $tasks = mpull($tasks, null, 'getPHID');
    $task_phids = array_keys($tasks);

    if ($task_phids) {
      $edge_type = PhabricatorEdgeConfig::TYPE_OBJECT_HAS_COLUMN;
      $edge_query = id(new PhabricatorEdgeQuery())
        ->withSourcePHIDs($task_phids)
        ->withEdgeTypes(array($edge_type))
        ->withDestinationPHIDs(mpull($columns, 'getPHID'));
      $edge_query->execute();
    }

    $task_map = array();
    $default_phid = $columns[0]->getPHID();
    foreach ($tasks as $task) {
      $task_phid = $task->getPHID();
      $column_phids = $edge_query->getDestinationPHIDs(array($task_phid));

      $column_phid = head($column_phids);
      $column_phid = nonempty($column_phid, $default_phid);

      $task_map[$column_phid][] = $task_phid;
    }

    $task_can_edit_map = id(new PhabricatorPolicyFilter())
      ->setViewer($viewer)
      ->requireCapabilities(array(PhabricatorPolicyCapability::CAN_EDIT))
      ->apply($tasks);

    $board_id = celerity_generate_unique_node_id();

    $board = id(new PHUIWorkboardView())
      ->setUser($viewer)
      ->setID($board_id);

    $this->initBehavior(
      'project-boards',
      array(
        'boardID' => $board_id,
        'projectPHID' => $project->getPHID(),
        'moveURI' => $this->getApplicationURI('move/'.$project->getID().'/'),
        'createURI' => '/maniphest/task/create/',
      ));

    $this->handles = ManiphestTaskListView::loadTaskHandles($viewer, $tasks);

    foreach ($columns as $column) {
      $panel = id(new PHUIWorkpanelView())
        ->setHeader($column->getDisplayName())
        ->setHeaderColor($column->getHeaderColor());
      if (!$column->isDefaultColumn()) {
        $panel->setEditURI('column/'.$column->getID().'/');
      }
      $panel->setHeaderAction(id(new PHUIIconView())
        ->setIconFont('fa-plus')
        ->setHref('/maniphest/task/create/')
        ->addSigil('column-add-task')
        ->setMetadata(
          array('columnPHID' => $column->getPHID())));

      $cards = id(new PHUIObjectItemListView())
        ->setUser($viewer)
        ->setFlush(true)
        ->setAllowEmptyList(true)
        ->addSigil('project-column')
        ->setMetadata(
          array(
            'columnPHID' => $column->getPHID(),
          ));
      $task_phids = idx($task_map, $column->getPHID(), array());
      foreach (array_select_keys($tasks, $task_phids) as $task) {
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

      if (!$task_phids) {
        $cards->addClass('project-column-empty');
      }

      $board->addPanel($panel);
    }

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $project,
      PhabricatorPolicyCapability::CAN_EDIT);

    $add_icon = id(new PHUIIconView())
      ->setIconFont('fa-plus bluegrey');

    $add_button = id(new PHUIButtonView())
      ->setText(pht('Add Column'))
      ->setIcon($add_icon)
      ->setTag('a')
      ->setHref($this->getApplicationURI('board/'.$this->id.'/edit/'))
      ->setDisabled(!$can_edit)
      ->setWorkflow(!$can_edit);

    Javelin::initBehavior(
      'boards-filter',
      array(
      ));

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
        $item->setHref(
          $this->getApplicationURI(
          'board/'.$this->id.'/filter/query/'.$key.'/'));
        $item->setWorkflow(true);
      } else {
        $item->setHref($engine->getQueryResultsPageURI($key));
      }

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
      ->addSigil('boards-filter-menu')

/*
      TODO: @chad, this looks really gnarly right now, at least in Safari.
      ->setDropdown(true)
*/

      ->setMetadata(
        array(
          'items' => hsprintf('%s', $filter_menu),
        ));

    $header_link = phutil_tag(
      'a',
      array(
        'href' => $this->getApplicationURI('view/'.$project->getID().'/')
      ),
      $project->getName());

    $header = id(new PHUIHeaderView())
      ->setHeader($header_link)
      ->setUser($viewer)
      ->setNoBackground(true)
      ->setImage($project->getProfileImageURI())
      ->setImageURL($this->getApplicationURI('view/'.$project->getID().'/'))
      ->addActionLink($filter_button)
      ->addActionLink($add_button)
      ->setPolicyObject($project);

    $board_box = id(new PHUIBoxView())
      ->appendChild($board)
      ->addClass('project-board-wrapper');

    return $this->buildApplicationPage(
      array(
        $header,
        $board_box,
      ),
      array(
        'title' => pht('%s Board', $project->getName()),
        'device' => true,
      ));
  }

}
