<?php

final class PhabricatorFavoritesMainController
  extends PhabricatorFavoritesController {

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
        ->setHref($this->getApplicationURI('personal/item/configure/'))
        ->setImageURI($viewer->getProfileImageURI())
        ->addAttribute(pht('Edit favorites for your personal account.')));

    $icon = id(new PHUIIconView())
      ->setIcon('fa-globe')
      ->setBackground('bg-blue');

    $menu->addItem(
      id(new PHUIObjectItemView())
        ->setHeader(pht('Global Menu Items'))
        ->setHref($this->getApplicationURI('global/item/configure/'))
        ->setImageIcon($icon)
        ->addAttribute(pht('Edit global default favorites for all users.')));

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Manage'));
    $crumbs->setBorder(true);

    $box = id(new PHUIObjectBoxView())
      ->setObjectList($menu);

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Manage Favorites'))
      ->setHeaderIcon('fa-star-o');

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFooter(array(
        $box,
      ));

    return $this->newPage()
      ->setTitle(pht('Manage'))
      ->setCrumbs($crumbs)
      ->appendChild($view);

  }

}
