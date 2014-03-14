<?php

final class PhabricatorOAuthServerConsoleController
  extends PhabricatorOAuthServerController {

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $menu = id(new PHUIObjectItemListView())
      ->setUser($viewer);

    $menu->addItem(
      id(new PHUIObjectItemView())
        ->setHeader(pht('Authorizations'))
        ->setHref($this->getApplicationURI('clientauthorization/'))
        ->addAttribute(
          pht(
            'Review your authorizations.')));

    $menu->addItem(
      id(new PHUIObjectItemView())
        ->setHeader(pht('Applications'))
        ->setHref($this->getApplicationURI('client/'))
        ->addAttribute(
          pht(
            'Create a new OAuth application.')));

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Console'));

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $menu,
      ),
      array(
        'title'  => pht('OAuth Server Console'),
        'device' => true,
      ));
  }

}
