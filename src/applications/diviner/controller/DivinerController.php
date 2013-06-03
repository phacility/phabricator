<?php

abstract class DivinerController extends PhabricatorController {

  protected function buildSideNavView() {
    $menu = $this->buildMenu();
    return AphrontSideNavFilterView::newFromMenu($menu);
  }

  protected function buildApplicationMenu() {
    return $this->buildMenu();
  }

  private function buildMenu() {
    $menu = new PhabricatorMenuView();

    id(new DivinerAtomSearchEngine())
      ->setViewer($this->getRequest()->getUser())
      ->addNavigationItems($menu);

    return $menu;
  }

}
