<?php

final class PhabricatorPeopleSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Users');
  }

  public function getApplicationClassName() {
    return 'PhabricatorPeopleApplication';
  }

  public function newQuery() {
    return id(new PhabricatorPeopleQuery())
      ->needPrimaryEmail(true)
      ->needProfileImage(true);
  }

  protected function buildCustomSearchFields() {
    return array(
      id(new PhabricatorSearchStringListField())
        ->setLabel(pht('Usernames'))
        ->setKey('usernames')
        ->setAliases(array('username')),
      id(new PhabricatorSearchTextField())
        ->setLabel(pht('Name Contains'))
        ->setKey('nameLike'),
      id(new PhabricatorSearchThreeStateField())
        ->setLabel(pht('Administrators'))
        ->setKey('isAdmin')
        ->setOptions(
          pht('(Show All)'),
          pht('Show Only Administrators'),
          pht('Hide Administrators')),
      id(new PhabricatorSearchThreeStateField())
        ->setLabel(pht('Disabled'))
        ->setKey('isDisabled')
        ->setOptions(
          pht('(Show All)'),
          pht('Show Only Disabled Users'),
          pht('Hide Disabled Users')),
      id(new PhabricatorSearchThreeStateField())
        ->setLabel(pht('Bots'))
        ->setKey('isSystemAgent')
        ->setOptions(
          pht('(Show All)'),
          pht('Show Only Bots'),
          pht('Hide Bots')),
      id(new PhabricatorSearchThreeStateField())
        ->setLabel(pht('Mailing Lists'))
        ->setKey('isMailingList')
        ->setOptions(
          pht('(Show All)'),
          pht('Show Only Mailing Lists'),
          pht('Hide Mailing Lists')),
      id(new PhabricatorSearchThreeStateField())
        ->setLabel(pht('Needs Approval'))
        ->setKey('needsApproval')
        ->setOptions(
          pht('(Show All)'),
          pht('Show Only Unapproved Users'),
          pht('Hide Unappproved Users')),
      id(new PhabricatorSearchDateField())
        ->setKey('createdStart')
        ->setLabel(pht('Joined After')),
      id(new PhabricatorSearchDateField())
        ->setKey('createdEnd')
        ->setLabel(pht('Joined Before')),
    );
  }

  protected function getDefaultFieldOrder() {
    return array(
      '...',
      'createdStart',
      'createdEnd',
    );
  }

  protected function buildQueryFromParameters(array $map) {
    $query = $this->newQuery();

    $viewer = $this->requireViewer();

    // If the viewer can't browse the user directory, restrict the query to
    // just the user's own profile. This is a little bit silly, but serves to
    // restrict users from creating a dashboard panel which essentially just
    // contains a user directory anyway.
    $can_browse = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $this->getApplication(),
      PeopleBrowseUserDirectoryCapability::CAPABILITY);
    if (!$can_browse) {
      $query->withPHIDs(array($viewer->getPHID()));
    }

    if ($map['usernames']) {
      $query->withUsernames($map['usernames']);
    }

    if ($map['nameLike']) {
      $query->withNameLike($map['nameLike']);
    }

    if ($map['isAdmin'] !== null) {
      $query->withIsAdmin($map['isAdmin']);
    }

    if ($map['isDisabled'] !== null) {
      $query->withIsDisabled($map['isDisabled']);
    }

    if ($map['isMailingList'] !== null) {
      $query->withIsMailingList($map['isMailingList']);
    }

    if ($map['isSystemAgent'] !== null) {
      $query->withIsSystemAgent($map['isSystemAgent']);
    }

    if ($map['needsApproval'] !== null) {
      $query->withIsApproved(!$map['needsApproval']);
    }

    if ($map['createdStart']) {
      $query->withDateCreatedAfter($map['createdStart']);
    }

    if ($map['createdEnd']) {
      $query->withDateCreatedBefore($map['createdEnd']);
    }

    return $query;
  }

  protected function getURI($path) {
    return '/people/'.$path;
  }

  protected function getBuiltinQueryNames() {
    $names = array(
      'active' => pht('Active'),
      'all' => pht('All'),
    );

    $viewer = $this->requireViewer();
    if ($viewer->getIsAdmin()) {
      $names['approval'] = pht('Approval Queue');
    }

    return $names;
  }

  public function buildSavedQueryFromBuiltin($query_key) {
    $query = $this->newSavedQuery();
    $query->setQueryKey($query_key);

    switch ($query_key) {
      case 'all':
        return $query;
      case 'active':
        return $query
          ->setParameter('isDisabled', false);
      case 'approval':
        return $query
          ->setParameter('needsApproval', true)
          ->setParameter('isDisabled', false);
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
  }

  protected function renderResultList(
    array $users,
    PhabricatorSavedQuery $query,
    array $handles) {

    assert_instances_of($users, 'PhabricatorUser');

    $request = $this->getRequest();
    $viewer = $this->requireViewer();

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
        ->addAttribute(phabricator_datetime($user->getDateCreated(), $viewer))
        ->addAttribute($email)
        ->setImageURI($user->getProfileImageURI());

      if ($is_approval && $primary_email) {
        $item->addAttribute($primary_email->getAddress());
      }

      if ($user->getIsDisabled()) {
        $item->addIcon('fa-ban', pht('Disabled'));
      }

      if (!$is_approval) {
        if (!$user->getIsApproved()) {
          $item->addIcon('fa-clock-o', pht('Needs Approval'));
        }
      }

      if ($user->getIsAdmin()) {
        $item->addIcon('fa-star', pht('Admin'));
      }

      if ($user->getIsSystemAgent()) {
        $item->addIcon('fa-desktop', pht('Bot'));
      }

      if ($user->getIsMailingList()) {
        $item->addIcon('fa-envelope-o', pht('Mailing List'));
      }

      if ($viewer->getIsAdmin()) {
        $user_id = $user->getID();
        if ($is_approval) {
          $item->addAction(
            id(new PHUIListItemView())
              ->setIcon('fa-ban')
              ->setName(pht('Disable'))
              ->setWorkflow(true)
              ->setHref($this->getApplicationURI('disapprove/'.$user_id.'/')));
          $item->addAction(
            id(new PHUIListItemView())
              ->setIcon('fa-thumbs-o-up')
              ->setName(pht('Approve'))
              ->setWorkflow(true)
              ->setHref($this->getApplicationURI('approve/'.$user_id.'/')));
        }
      }

      $list->addItem($item);
    }

    $result = new PhabricatorApplicationSearchResultView();
    $result->setObjectList($list);
    $result->setNoDataString(pht('No accounts found.'));

    return $result;
  }

}
