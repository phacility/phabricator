<?php

final class PhabricatorPeopleListController extends PhabricatorPeopleController
  implements PhabricatorApplicationSearchResultsControllerInterface {

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
    $request = $this->getRequest();
    $controller = id(new PhabricatorApplicationSearchController($request))
      ->setQueryKey($this->key)
      ->setSearchEngine(new PhabricatorPeopleSearchEngine())
      ->setNavigation($this->buildSideNavView());

    return $this->delegateToController($controller);
  }

  public function renderResultsList(
    array $users,
    PhabricatorSavedQuery $query) {

    assert_instances_of($users, 'PhabricatorUser');

    $request = $this->getRequest();
    $viewer = $request->getUser();

    $list = new PHUIObjectItemListView();

    foreach ($users as $user) {
      $primary_email = $user->loadPrimaryEmail();
      if ($primary_email && $primary_email->getIsVerified()) {
        $email = pht('Verified');
      } else {
        $email = pht('Unverified');
      }

      $user_handle = new PhabricatorObjectHandle();
      $user_handle->setImageURI($user->loadProfileImageURI());

      $item = new PHUIObjectItemView();
      $item->setHeader($user->getFullName())
        ->setHref('/p/'.$user->getUsername().'/')
        ->addAttribute(hsprintf('%s %s',
            phabricator_date($user->getDateCreated(), $viewer),
            phabricator_time($user->getDateCreated(), $viewer)))
        ->addAttribute($email);

      if ($user->getIsDisabled()) {
        $item->addIcon('disable', pht('Disabled'));
      }

      if ($user->getIsAdmin()) {
        $item->addIcon('highlight', pht('Admin'));
      }

      if ($user->getIsSystemAgent()) {
        $item->addIcon('computer', pht('System Agent'));
      }

      if ($viewer->getIsAdmin()) {
        $uid = $user->getID();
        $item->addAction(
          id(new PHUIListItemView())
            ->setIcon('edit')
            ->setHref($this->getApplicationURI('edit/'.$uid.'/')));
      }

      $list->addItem($item);
    }

    return $list;
  }
}
