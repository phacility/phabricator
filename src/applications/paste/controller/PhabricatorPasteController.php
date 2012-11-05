<?php

abstract class PhabricatorPasteController extends PhabricatorController {

  public function buildSideNavView(PhabricatorPaste $paste = null) {
    $user = $this->getRequest()->getUser();

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI('filter/')));

    if ($paste) {
      $nav->addFilter('paste', 'P'.$paste->getID(), '/P'.$paste->getID());
      $nav->addSpacer();
    }

    $nav->addLabel('Create');
    $nav->addFilter(
      'edit',
      'New Paste',
      $this->getApplicationURI(),
      $relative = false,
      $class = ($user->isLoggedIn() ? null : 'disabled'));

    $nav->addSpacer();
    $nav->addLabel('Pastes');
    if ($user->isLoggedIn()) {
      $nav->addFilter('my', 'My Pastes');
    }
    $nav->addFilter('all', 'All Pastes');

    return $nav;
  }

}
