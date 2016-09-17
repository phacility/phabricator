<?php

abstract class PhabricatorGuideController extends PhabricatorController {

  public function buildSideNavView($filter = null, $for_app = false) {

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));
    $nav->addLabel(pht('Guides'));

    $modules = PhabricatorGuideModule::getEnabledModules();
    foreach ($modules as $key => $module) {
      $nav->addFilter($key.'/', $module->getModuleName());
    }

    return $nav;
  }

  public function buildApplicationMenu() {
    return $this->buildSideNavView(null, true)->getMenu();
  }

}
