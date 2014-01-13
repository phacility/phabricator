<?php

final class PhabricatorProjectBoardController
  extends PhabricatorProjectController {

  private $id;

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
      ->withIDs(array($this->id))
      ->executeOne();
    if (!$project) {
      return new Aphront404Response();
    }

    $columns = id(new PhabricatorProjectColumnQuery())
      ->setViewer($viewer)
      ->withProjectPHIDs(array($project->getPHID()))
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
      ->withStatus(ManiphestTaskQuery::STATUS_OPEN)
      ->setOrderBy(ManiphestTaskQuery::ORDER_PRIORITY)
      ->execute();
    $tasks = mpull($tasks, null, 'getPHID');

    $task_map = array();
    $default_phid = $columns[0]->getPHID();

    foreach ($tasks as $task) {
      $task_map[$default_phid][] = $task->getPHID();
    }

    $board = id(new PHUIWorkboardView())
      ->setUser($viewer)
      ->setFluidishLayout(true);

    foreach ($columns as $column) {
      $panel = id(new PHUIWorkpanelView())
        ->setHeader($column->getDisplayName())
        ->setHeaderColor($column->getHeaderColor())
        ->setEditURI('edit/'.$column->getID().'/');

      $cards = id(new PHUIObjectItemListView())
        ->setUser($viewer)
        ->setCards(true)
        ->setFlush(true);
      $task_phids = idx($task_map, $column->getPHID(), array());
      foreach (array_select_keys($tasks, $task_phids) as $task) {
        $cards->addItem($this->renderTaskCard($task));
      }
      $panel->setCards($cards);

      $board->addPanel($panel);
    }

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(
      $project->getName(),
      $this->getApplicationURI('view/'.$project->getID().'/'));
    $crumbs->addTextCrumb(pht('Board'));

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $project,
      PhabricatorPolicyCapability::CAN_EDIT);

    $actions = id(new PhabricatorActionListView())
      ->setUser($viewer)
      ->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Add Column/Milestone/Sprint'))
          ->setHref($this->getApplicationURI('board/'.$this->id.'/edit/'))
          ->setIcon('create')
          ->setDisabled(!$can_edit)
          ->setWorkflow(!$can_edit));

    $plist = id(new PHUIPropertyListView());
    // TODO: Need this to get actions to render.
    $plist->addProperty(pht('Ignore'), pht('This Property'));
    $plist->setActionList($actions);

    $header = id(new PHUIObjectBoxView())
      ->setHeaderText($project->getName())
      ->addPropertyList($plist);

    $board_box = id(new PHUIBoxView())
      ->appendChild($board)
      ->addMargin(PHUI::MARGIN_LARGE);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $header,
        $board_box,
      ),
      array(
        'title' => pht('%s Board', $project->getName()),
        'device' => true,
      ));
  }

  private function renderTaskCard(ManiphestTask $task) {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $color_map = ManiphestTaskPriority::getColorMap();
    $bar_color = idx($color_map, $task->getPriority(), 'grey');

    // TODO: Batch this earlier on.
    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $task,
      PhabricatorPolicyCapability::CAN_EDIT);

    return id(new PHUIObjectItemView())
      ->setObjectName('T'.$task->getID())
      ->setHeader($task->getTitle())
      ->setGrippable($can_edit)
      ->setHref('/T'.$task->getID())
      ->addAction(
        id(new PHUIListItemView())
          ->setName(pht('Edit'))
          ->setIcon('edit')
          ->setHref('/maniphest/task/edit/'.$task->getID().'/')
          ->setWorkflow(true))
      ->setBarColor($bar_color);
  }

}
