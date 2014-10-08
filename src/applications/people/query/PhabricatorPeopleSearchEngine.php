<?php

final class PhabricatorPeopleSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Users');
  }

  public function getApplicationClassName() {
    return 'PhabricatorPeopleApplication';
  }

  public function getCustomFieldObject() {
    return new PhabricatorUser();
  }

  public function buildSavedQueryFromRequest(AphrontRequest $request) {
    $saved = new PhabricatorSavedQuery();

    $saved->setParameter('usernames', $request->getStrList('usernames'));
    $saved->setParameter('nameLike', $request->getStr('nameLike'));
    $saved->setParameter('isAdmin', $request->getStr('isAdmin'));
    $saved->setParameter('isDisabled', $request->getStr('isDisabled'));
    $saved->setParameter('isSystemAgent', $request->getStr('isSystemAgent'));
    $saved->setParameter('needsApproval', $request->getStr('needsApproval'));
    $saved->setParameter('createdStart', $request->getStr('createdStart'));
    $saved->setParameter('createdEnd', $request->getStr('createdEnd'));

    $this->readCustomFieldsFromRequest($request, $saved);

    return $saved;
  }

  public function buildQueryFromSavedQuery(PhabricatorSavedQuery $saved) {
    $query = id(new PhabricatorPeopleQuery())
      ->needPrimaryEmail(true)
      ->needProfileImage(true);

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

    $usernames = $saved->getParameter('usernames', array());
    if ($usernames) {
      $query->withUsernames($usernames);
    }

    $like = $saved->getParameter('nameLike');
    if ($like) {
      $query->withNameLike($like);
    }

    $is_admin = $saved->getParameter('isAdmin');
    $is_disabled = $saved->getParameter('isDisabled');
    $is_system_agent = $saved->getParameter('isSystemAgent');
    $needs_approval = $saved->getParameter('needsApproval');
    $no_disabled = $saved->getParameter('noDisabled');

    if ($is_admin) {
      $query->withIsAdmin(true);
    }

    if ($is_disabled) {
      $query->withIsDisabled(true);
    } else if ($no_disabled) {
      $query->withIsDisabled(false);
    }

    if ($is_system_agent) {
      $query->withIsSystemAgent(true);
    }

    if ($needs_approval) {
      $query->withIsApproved(false);
    }

    $start = $this->parseDateTime($saved->getParameter('createdStart'));
    $end = $this->parseDateTime($saved->getParameter('createdEnd'));

    if ($start) {
      $query->withDateCreatedAfter($start);
    }

    if ($end) {
      $query->withDateCreatedBefore($end);
    }

    $this->applyCustomFieldsToQuery($query, $saved);

    return $query;
  }

  public function buildSearchForm(
    AphrontFormView $form,
    PhabricatorSavedQuery $saved) {

    $usernames = $saved->getParameter('usernames', array());
    $like = $saved->getParameter('nameLike');

    $is_admin = $saved->getParameter('isAdmin');
    $is_disabled = $saved->getParameter('isDisabled');
    $is_system_agent = $saved->getParameter('isSystemAgent');
    $needs_approval = $saved->getParameter('needsApproval');

    $form
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setName('usernames')
          ->setLabel(pht('Usernames'))
          ->setValue(implode(', ', $usernames)))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setName('nameLike')
          ->setLabel(pht('Name Contains'))
          ->setValue($like))
      ->appendChild(
        id(new AphrontFormCheckboxControl())
          ->setLabel('Role')
          ->addCheckbox(
            'isAdmin',
            1,
            pht('Show only administrators.'),
            $is_admin)
          ->addCheckbox(
            'isDisabled',
            1,
            pht('Show only disabled users.'),
            $is_disabled)
          ->addCheckbox(
            'isSystemAgent',
            1,
            pht('Show only bots.'),
            $is_system_agent)
          ->addCheckbox(
            'needsApproval',
            1,
            pht('Show only users who need approval.'),
            $needs_approval));

    $this->appendCustomFieldsToForm($form, $saved);

    $this->buildDateRange(
      $form,
      $saved,
      'createdStart',
      pht('Joined After'),
      'createdEnd',
      pht('Joined Before'));
  }

  protected function getURI($path) {
    return '/people/'.$path;
  }

  public function getBuiltinQueryNames() {
    $names = array(
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
      case 'approval':
        return $query
          ->setParameter('needsApproval', true)
          ->setParameter('noDisabled', true);
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
        $item->addIcon('fa-desktop', pht('Bot/Script'));
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

    return $list;
  }

}
