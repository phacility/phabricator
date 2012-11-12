<?php

abstract class PhabricatorPeopleController extends PhabricatorController {

  public function buildSideNavView() {
    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    $is_admin = $this->getRequest()->getUser()->getIsAdmin();

    if ($is_admin) {
      $nav->addLabel('Create Users');
      $nav->addFilter('edit', 'Create New User');
      if (PhabricatorEnv::getEnvConfig('ldap.auth-enabled') === true) {
        $nav->addFilter('ldap', 'Import from LDAP');
      }
      $nav->addSpacer();
    }

    $nav->addLabel('Directory');
    $nav->addFilter('people', 'User Directory', $this->getApplicationURI());

    if ($is_admin) {
      $nav->addSpacer();
      $nav->addLabel('Logs');
      $nav->addFilter('logs', 'Activity Logs');
    }

    return $nav;
  }

}
