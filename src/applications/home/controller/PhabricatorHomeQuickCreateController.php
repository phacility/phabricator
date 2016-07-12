<?php

final class PhabricatorHomeQuickCreateController
  extends PhabricatorHomeController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $items = PhabricatorQuickActions::loadMenuItemsForUser($viewer);

    $list = id(new PHUIObjectItemListView())
      ->setUser($viewer);

    foreach ($items as $item) {
      $list->addItem(
        id(new PHUIObjectItemView())
          ->setHeader($item->getName())
          ->setWorkflow($item->getWorkflow())
          ->setHref($item->getHref()));
    }

    $title = pht('Quick Create');

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Quick Create'));
    $crumbs->setBorder(true);

    $box = id(new PHUIObjectBoxView())
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setObjectList($list);

    $header = id(new PHUIHeaderView())
      ->setHeader($title)
      ->setHeaderIcon('fa-plus-square');

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFooter($box);

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($view);

  }

}
