<?php

final class PhabricatorHomeQuickCreateController
  extends PhabricatorHomeController {

  public function processRequest() {
    $viewer = $this->getRequest()->getUser();

    $applications = id(new PhabricatorApplicationQuery())
      ->setViewer($viewer)
      ->withInstalled(true)
      ->execute();

    $items = array();
    foreach ($applications as $application) {
      $app_items = $application->getQuickCreateItems($viewer);
      foreach ($app_items as $app_item) {
        $items[] = $app_item;
      }
    }

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
