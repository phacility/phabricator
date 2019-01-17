<?php

abstract class PhabricatorAuthProviderController
  extends PhabricatorAuthController {

  protected function newNavigation() {
    $viewer = $this->getViewer();

    $nav = id(new AphrontSideNavFilterView())
      ->setBaseURI(new PhutilURI($this->getApplicationURI()))
      ->setViewer($viewer);

    $nav->addMenuItem(
      id(new PHUIListItemView())
        ->setName(pht('Authentication'))
        ->setType(PHUIListItemView::TYPE_LABEL));

    $nav->addMenuItem(
      id(new PHUIListItemView())
        ->setKey('login')
        ->setName(pht('Login and Registration'))
        ->setType(PHUIListItemView::TYPE_LINK)
        ->setHref($this->getApplicationURI('/'))
        ->setIcon('fa-key'));

    $nav->addMenuItem(
      id(new PHUIListItemView())
        ->setKey('mfa')
        ->setName(pht('Multi-Factor'))
        ->setType(PHUIListItemView::TYPE_LINK)
        ->setHref($this->getApplicationURI('mfa/'))
        ->setIcon('fa-mobile'));

    $nav->addMenuItem(
      id(new PHUIListItemView())
        ->setName(pht('Onboarding'))
        ->setType(PHUIListItemView::TYPE_LABEL));

    $nav->addMenuItem(
      id(new PHUIListItemView())
        ->setKey('message')
        ->setName(pht('Customize Messages'))
        ->setType(PHUIListItemView::TYPE_LINK)
        ->setHref($this->getApplicationURI('message/'))
        ->setIcon('fa-commenting-o'));


    $nav->selectFilter(null);

    return $nav;
  }

  public function buildApplicationMenu() {
    return $this->newNavigation()->getMenu();
  }

}
