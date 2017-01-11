<?php

final class PhabricatorHomeMenuController extends PhabricatorHomeController {

  public function shouldAllowPublic() {
    return false;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $menu = id(new PHUIObjectItemListView())
      ->setUser($viewer);

    $menu->addItem(
      id(new PHUIObjectItemView())
        ->setHeader(pht('Personal Menu Items'))
        ->setHref($this->getApplicationURI('menu/personal/item/configure/'))
        ->setImageURI($viewer->getProfileImageURI())
        ->addAttribute(pht('Edit the menu for your personal account.')));

    $icon = id(new PHUIIconView())
      ->setIcon('fa-globe')
      ->setBackground('bg-blue');

    $menu->addItem(
      id(new PHUIObjectItemView())
        ->setHeader(pht('Global Menu Items'))
        ->setHref($this->getApplicationURI('menu/global/item/configure/'))
        ->setImageIcon($icon)
        ->addAttribute(pht('Edit the global default menu for all users.')));

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Manage'));
    $crumbs->setBorder(true);

    $box = id(new PHUIObjectBoxView())
      ->setObjectList($menu);

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Manage Home Menu'))
      ->setHeaderIcon('fa-home');

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFooter(array(
        $box,
      ));

    return $this->newPage()
      ->setTitle(pht('Manage Home Menu'))
      ->setCrumbs($crumbs)
      ->appendChild($view);

  }

}
