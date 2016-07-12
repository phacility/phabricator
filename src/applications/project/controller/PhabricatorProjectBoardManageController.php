<?php

final class PhabricatorProjectBoardManageController
  extends PhabricatorProjectBoardController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $board_id = $request->getURIData('projectID');

    $board = id(new PhabricatorProjectQuery())
      ->setViewer($viewer)
      ->withIDs(array($board_id))
      ->needImages(true)
      ->executeOne();
    if (!$board) {
      return new Aphront404Response();
    }
    $this->setProject($board);

    // Perform layout of no tasks to load and populate the columns in the
    // correct order.
    $layout_engine = id(new PhabricatorBoardLayoutEngine())
      ->setViewer($viewer)
      ->setBoardPHIDs(array($board->getPHID()))
      ->setObjectPHIDs(array())
      ->setFetchAllBoards(true)
      ->executeLayout();

    $columns = $layout_engine->getColumns($board->getPHID());

    $board_id = $board->getID();

    $header = $this->buildHeaderView($board);
    $actions = $this->buildActionView($board);
    $properties = $this->buildPropertyView($board);

    $properties->setActionList($actions);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Workboard'), "/project/board/{$board_id}/");
    $crumbs->addTextCrumb(pht('Manage'));

    $box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($properties);

    $nav = $this->getProfileMenu();

    $title = array(
      pht('Manage Workboard'),
      $board->getDisplayName(),
    );

    $columns_list = $this->buildColumnsList($board, $columns);

    return $this->newPage()
      ->setTitle($title)
      ->setNavigation($nav)
      ->setCrumbs($crumbs)
      ->appendChild(
        array(
          $box,
          $columns_list,
        ));
  }

  private function buildHeaderView(PhabricatorProject $board) {
    $viewer = $this->getRequest()->getUser();

    $header = id(new PHUIHeaderView())
      ->setUser($viewer)
      ->setHeader(pht('Workboard: %s', $board->getDisplayName()));

    return $header;
  }

  private function buildActionView(PhabricatorProject $board) {
    $viewer = $this->getRequest()->getUser();
    $id = $board->getID();

    $actions = id(new PhabricatorActionListView())
      ->setUser($viewer);

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $board,
      PhabricatorPolicyCapability::CAN_EDIT);

    $reorder_uri = $this->getApplicationURI("board/{$id}/reorder/");

    $actions->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-exchange')
        ->setName(pht('Reorder Columns'))
        ->setHref($reorder_uri)
        ->setDisabled(!$can_edit)
        ->setWorkflow(true));

    $background_uri = $this->getApplicationURI("board/{$id}/background/");

    $actions->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-paint-brush')
        ->setName(pht('Change Background Color'))
        ->setHref($background_uri)
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    $disable_uri = $this->getApplicationURI("board/{$id}/disable/");

    $actions->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-ban')
        ->setName(pht('Disable Board'))
        ->setHref($disable_uri)
        ->setDisabled(!$can_edit)
        ->setWorkflow(true));

    return $actions;
  }

  private function buildPropertyView(
    PhabricatorProject $board) {
    $viewer = $this->getRequest()->getUser();

    $properties = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setObject($board);

    $background = $board->getDisplayWorkboardBackgroundColor();
    if ($background !== null) {
      $map = PhabricatorProjectWorkboardBackgroundColor::getOptions();
      $map = ipull($map, 'name');

      $name = idx($map, $background, $background);
      $properties->addProperty(pht('Background Color'), $name);
    }

    return $properties;
  }

  private function buildColumnsList(
    PhabricatorProject $board,
    array $columns) {
    assert_instances_of($columns, 'PhabricatorProjectColumn');

    $board_id = $board->getID();

    $view = id(new PHUIObjectItemListView())
      ->setNoDataString(pht('This board has no columns.'));

    foreach ($columns as $column) {
      $column_id = $column->getID();

      $proxy = $column->getProxy();
      if ($proxy && !$proxy->isMilestone()) {
        continue;
      }

      $detail_uri = "/project/board/{$board_id}/column/{$column_id}/";

      $item = id(new PHUIObjectItemView())
        ->setHeader($column->getDisplayName())
        ->setHref($detail_uri);

      if ($column->isHidden()) {
        $item->setDisabled(true);
      }

      $view->addItem($item);
    }

    return id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Columns'))
      ->setObjectList($view);
  }


}
