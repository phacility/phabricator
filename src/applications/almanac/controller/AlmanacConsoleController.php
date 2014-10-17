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
        ->addAttribute(
          pht(
            'Manage Almanac services.')));

    $menu->addItem(
      id(new PHUIObjectItemView())
        ->setHeader(pht('Devices'))
        ->setHref($this->getApplicationURI('device/'))
        ->addAttribute(
          pht(
            'Manage Almanac devices.')));

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Console'));

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $menu,
      ),
      array(
        'title'  => pht('Almanac Console'),
      ));
  }

}
