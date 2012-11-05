<?php

abstract class PhabricatorMacroController
  extends PhabricatorController {

  protected function buildSideNavView(PhabricatorFileImageMacro $macro = null) {
    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    $nav->addLabel('Create');
    $nav->addFilter('edit', 'Create Macro');

    $nav->addSpacer();

    $nav->addLabel('Macros');
    $nav->addFilter('', 'All Macros');

    return $nav;
  }

}
