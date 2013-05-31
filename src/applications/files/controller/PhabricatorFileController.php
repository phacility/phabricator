<?php

abstract class PhabricatorFileController extends PhabricatorController {

  public function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();
    $crumbs->addAction(
      id(new PhabricatorMenuItemView())
        ->setName(pht('Upload File'))
        ->setIcon('create') // TODO: Get @chad to build an "upload" icon.
        ->setHref($this->getApplicationURI('/upload/')));

    return $crumbs;
  }

  protected function buildSideNavView() {
    $menu = $this->buildMenu($for_devices = false);
    return AphrontSideNavFilterView::newFromMenu($menu);
  }

  protected function buildApplicationMenu() {
    return $this->buildMenu($for_devices = true);
  }

  private function buildMenu($for_devices) {
    $menu = new PhabricatorMenuView();

    if ($for_devices) {
      $menu->newLink(pht('Upload File'), $this->getApplicationURI('/upload/'));
    }

    id(new PhabricatorFileSearchEngine())
      ->setViewer($this->getRequest()->getUser())
      ->addNavigationItems($menu);

    return $menu;
  }


}
