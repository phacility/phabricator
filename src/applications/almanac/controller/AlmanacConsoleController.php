<?php

final class AlmanacConsoleController extends AlmanacController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $menu = id(new PHUIObjectItemListView())
      ->setUser($viewer);

    $menu->addItem(
      id(new PHUIObjectItemView())
        ->setHeader(pht('Services'))
        ->setHref($this->getApplicationURI('service/'))
        ->setFontIcon('fa-plug')
        ->addAttribute(pht('Manage Almanac services.')));

    $menu->addItem(
      id(new PHUIObjectItemView())
        ->setHeader(pht('Devices'))
        ->setHref($this->getApplicationURI('device/'))
        ->setFontIcon('fa-server')
        ->addAttribute(pht('Manage Almanac devices.')));

    $menu->addItem(
      id(new PHUIObjectItemView())
        ->setHeader(pht('Networks'))
        ->setHref($this->getApplicationURI('network/'))
        ->setFontIcon('fa-globe')
        ->addAttribute(pht('Manage Almanac networks.')));

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
        'title'  => pht('Almanac Console'),
      ));
  }

}
