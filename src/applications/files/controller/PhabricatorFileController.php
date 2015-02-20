<?php

abstract class PhabricatorFileController extends PhabricatorController {

  protected function buildSideNavView() {
    $menu = $this->buildMenu($for_devices = false);
    return AphrontSideNavFilterView::newFromMenu($menu);
  }

  public function buildApplicationMenu() {
    return $this->buildMenu($for_devices = true);
  }

  private function buildMenu($for_devices) {
    $menu = new PHUIListView();

    if ($for_devices) {
      $menu->newLink(pht('Upload File'), $this->getApplicationURI('/upload/'));
    }

    id(new PhabricatorFileSearchEngine())
      ->setViewer($this->getRequest()->getUser())
      ->addNavigationItems($menu);

    return $menu;
  }


}
