<?php

final class PhabricatorPeopleListController
  extends PhabricatorPeopleController {

  private $key;

  public function shouldAllowPublic() {
    return true;
  }

  public function shouldRequireAdmin() {
    return false;
  }

  public function willProcessRequest(array $data) {
    $this->key = idx($data, 'key');
  }

  public function processRequest() {
    $this->requireApplicationCapability(
      PeopleBrowseUserDirectoryCapability::CAPABILITY);

    $controller = id(new PhabricatorApplicationSearchController())
      ->setQueryKey($this->key)
      ->setSearchEngine(new PhabricatorPeopleSearchEngine())
      ->setNavigation($this->buildSideNavView());

    return $this->delegateToController($controller);
  }

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();
    $viewer = $this->getRequest()->getUser();

    $can_create = $this->hasApplicationCapability(
      PeopleCreateUsersCapability::CAPABILITY);
    if ($can_create) {
      $crumbs->addAction(
        id(new PHUIListItemView())
        ->setName(pht('Create New User'))
        ->setHref($this->getApplicationURI('create/'))
        ->setIcon('fa-plus-square'));
    } else if ($viewer->getIsAdmin()) {
      $crumbs->addAction(
        id(new PHUIListItemView())
        ->setName(pht('Create New Bot'))
        ->setHref($this->getApplicationURI('new/bot/'))
        ->setIcon('fa-plus-square'));
    }

    return $crumbs;
  }


}
