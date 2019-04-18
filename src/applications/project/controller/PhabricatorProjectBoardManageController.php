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
    $curtain = $this->buildCurtainView($board);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Workboard'), $board->getWorkboardURI());
    $crumbs->addTextCrumb(pht('Manage'));
    $crumbs->setBorder(true);

    $nav = $this->newNavigation(
      $board,
      PhabricatorProject::ITEM_WORKBOARD);
    $columns_list = $this->buildColumnsList($board, $columns);

    require_celerity_resource('project-view-css');

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->addClass('project-view-home')
      ->addClass('project-view-people-home')
      ->setCurtain($curtain)
      ->setMainColumn($columns_list);

    $title = array(
      pht('Manage Workboard'),
      $board->getDisplayName(),
    );

    return $this->newPage()
      ->setTitle($title)
      ->setNavigation($nav)
      ->setCrumbs($crumbs)
      ->appendChild($view);
  }

  private function buildHeaderView(PhabricatorProject $board) {
    $viewer = $this->getViewer();

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Workboard: %s', $board->getDisplayName()))
      ->setUser($viewer);

    return $header;
  }

  private function buildCurtainView(PhabricatorProject $board) {
    $viewer = $this->getViewer();
    $id = $board->getID();

    $curtain = $this->newCurtainView();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $board,
      PhabricatorPolicyCapability::CAN_EDIT);

    $disable_uri = $this->getApplicationURI("board/{$id}/disable/");

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-ban')
        ->setName(pht('Disable Workboard'))
        ->setHref($disable_uri)
        ->setDisabled(!$can_edit)
        ->setWorkflow(true));

    return $curtain;
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
        $item->addAttribute(pht('Hidden'));
        $item->setImageIcon('fa-columns grey');
      } else {
        $item->addAttribute(pht('Visible'));
        $item->setImageIcon('fa-columns');
      }

      $view->addItem($item);
    }

    return id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Columns'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setObjectList($view);
  }


}
