<?php

final class PhabricatorProjectBoardViewController
  extends PhabricatorProjectBoardController {

  private $id;
  private $handles;

  public function shouldAllowPublic() {
    return true;
  }

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
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

    $tasks = id(new ManiphestTaskQuery())
      ->setViewer($viewer)
      ->withAllProjects(array($project->getPHID()))
      ->withStatuses(ManiphestTaskStatus::getOpenStatusConstants())
      ->setOrderBy(ManiphestTaskQuery::ORDER_PRIORITY)
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
      ->setFluidishLayout(true)
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
        ->setSpriteSheet(PHUIIconView::SPRITE_ACTIONS)
        ->setSpriteIcon('new-grey')
        ->setHref('/maniphest/task/create/')
        ->addSigil('column-add-task')
        ->setMetadata(
          array('columnPHID' => $column->getPHID())));

      $cards = id(new PHUIObjectItemListView())
        ->setUser($viewer)
        ->setCards(true)
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

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Board'));

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $project,
      PhabricatorPolicyCapability::CAN_EDIT);

    $actions = id(new PhabricatorActionListView())
      ->setUser($viewer)
      ->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Add Column'))
          ->setHref($this->getApplicationURI('board/'.$this->id.'/edit/'))
          ->setIcon('create')
          ->setDisabled(!$can_edit)
          ->setWorkflow(!$can_edit));

    $plist = id(new PHUIPropertyListView());

    // TODO: Need this to get actions to render.
    $plist->addProperty(
      pht('Project Boards'),
      phutil_tag(
        'em',
        array(),
        pht(
          'This feature is beta, but should mostly work.')));
    $plist->setActionList($actions);

    $header = id(new PHUIHeaderView())
      ->setHeader($project->getName())
      ->setUser($viewer)
      ->setImage($project->getProfileImageURI())
      ->setPolicyObject($project);

    $box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($plist);

    $board_box = id(new PHUIBoxView())
      ->appendChild($board)
      ->addMargin(PHUI::MARGIN_LARGE);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $box,
        $board_box,
      ),
      array(
        'title' => pht('%s Board', $project->getName()),
        'device' => true,
      ));
  }

}
