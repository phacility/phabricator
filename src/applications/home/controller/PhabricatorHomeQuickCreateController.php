<?php

final class PhabricatorHomeQuickCreateController
  extends PhabricatorHomeController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $items = $this->getCurrentApplication()->loadAllQuickCreateItems($viewer);

    $list = id(new PHUIObjectItemListView())
      ->setUser($viewer);

    foreach ($items as $item) {
      $list->addItem(
        id(new PHUIObjectItemView())
          ->setHeader($item->getName())
          ->setWorkflow($item->getWorkflow())
          ->setHref($item->getHref()));
    }

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Quick Create'));

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $list,
      ),
      array(
        'title' => pht('Quick Create'),
      ));
  }

}
