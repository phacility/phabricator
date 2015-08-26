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
        ->setFontIcon('fa-align-left')
        ->addAttribute(pht('Manage Nuance queues.')));

    $menu->addItem(
      id(new PHUIObjectItemView())
        ->setHeader(pht('Sources'))
        ->setHref($this->getApplicationURI('source/'))
        ->setFontIcon('fa-filter')
        ->addAttribute(pht('Manage Nuance sources.')));

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Console'));

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Console'))
      ->setObjectList($menu);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $box,
      ),
      array(
        'title'  => pht('Nuance Console'),
      ));
  }

}
