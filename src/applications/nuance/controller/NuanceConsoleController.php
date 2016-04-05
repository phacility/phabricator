<?php

final class NuanceConsoleController extends NuanceController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $menu = id(new PHUIObjectItemListView())
      ->setUser($viewer);

    $menu->addItem(
      id(new PHUIObjectItemView())
        ->setHeader(pht('Queues'))
        ->setHref($this->getApplicationURI('queue/'))
        ->setIcon('fa-align-left')
        ->addAttribute(pht('Manage Nuance queues.')));

    $menu->addItem(
      id(new PHUIObjectItemView())
        ->setHeader(pht('Sources'))
        ->setHref($this->getApplicationURI('source/'))
        ->setIcon('fa-filter')
        ->addAttribute(pht('Manage Nuance sources.')));

    $menu->addItem(
      id(new PHUIObjectItemView())
        ->setHeader(pht('Items'))
        ->setHref($this->getApplicationURI('item/'))
        ->setIcon('fa-clone')
        ->addAttribute(pht('Manage Nuance items.')));

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Console'));
    $crumbs->setBorder(true);

    $box = id(new PHUIObjectBoxView())
      ->setObjectList($menu);

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Nuance Console'))
      ->setHeaderIcon('fa-fax');

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFooter(array(
        $box,
      ));

    return $this->newPage()
      ->setTitle(pht('Nuance Console'))
      ->setCrumbs($crumbs)
      ->appendChild($view);
  }

}
