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
    $viewer = $request->getUser();

    $this->requireApplicationCapability(
      PeopleCapabilityBrowseUserDirectory::CAPABILITY);

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

    $is_approval = ($query->getQueryKey() == 'approval');

    foreach ($users as $user) {
      $primary_email = $user->loadPrimaryEmail();
      if ($primary_email && $primary_email->getIsVerified()) {
        $email = pht('Verified');
      } else {
        $email = pht('Unverified');
      }

      $item = new PHUIObjectItemView();
      $item->setHeader($user->getFullName())
        ->setHref('/p/'.$user->getUsername().'/')
        ->addAttribute(hsprintf('%s %s',
            phabricator_date($user->getDateCreated(), $viewer),
            phabricator_time($user->getDateCreated(), $viewer)))
        ->addAttribute($email)
        ->setImageURI($user->getProfileImageURI());

      if ($is_approval && $primary_email) {
        $item->addAttribute($primary_email->getAddress());
      }

      if ($user->getIsDisabled()) {
        $item->addIcon('disable', pht('Disabled'));
      }

      if (!$is_approval) {
        if (!$user->getIsApproved()) {
          $item->addIcon('perflab-grey', pht('Needs Approval'));
        }
      }

      if ($user->getIsAdmin()) {
        $item->addIcon('highlight', pht('Admin'));
      }

      if ($user->getIsSystemAgent()) {
        $item->addIcon('computer', pht('System Agent'));
      }

      if ($viewer->getIsAdmin()) {
        $user_id = $user->getID();
        if ($is_approval) {
          $item->addAction(
            id(new PHUIListItemView())
              ->setIcon('disable')
              ->setName(pht('Disable'))
              ->setWorkflow(true)
              ->setHref($this->getApplicationURI('disable/'.$user_id.'/')));
          $item->addAction(
            id(new PHUIListItemView())
              ->setIcon('like')
              ->setName(pht('Approve'))
              ->setWorkflow(true)
              ->setHref($this->getApplicationURI('approve/'.$user_id.'/')));
        } else {
          $item->addAction(
            id(new PHUIListItemView())
              ->setIcon('edit')
              ->setHref($this->getApplicationURI('edit/'.$user_id.'/')));
        }
      }

      $list->addItem($item);
    }

    return $list;
  }
}
