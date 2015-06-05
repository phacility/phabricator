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

    $saved->setParameter(
      'isAdmin',
      $this->readBoolFromRequest($request, 'isAdmin'));

    $saved->setParameter(
      'isDisabled',
      $this->readBoolFromRequest($request, 'isDisabled'));

    $saved->setParameter(
      'isSystemAgent',
      $this->readBoolFromRequest($request, 'isSystemAgent'));

    $saved->setParameter(
      'isMailingList',
      $this->readBoolFromRequest($request, 'isMailingList'));

    $saved->setParameter(
      'needsApproval',
      $this->readBoolFromRequest($request, 'needsApproval'));

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
    $is_mailing_list = $saved->getParameter('isMailingList');
    $needs_approval = $saved->getParameter('needsApproval');

    if ($is_admin !== null) {
      $query->withIsAdmin($is_admin);
    }

    if ($is_disabled !== null) {
      $query->withIsDisabled($is_disabled);
    }

    if ($is_system_agent !== null) {
      $query->withIsSystemAgent($is_system_agent);
    }

    if ($is_mailing_list !== null) {
      $query->withIsMailingList($is_mailing_list);
    }

    if ($needs_approval !== null) {
      $query->withIsApproved(!$needs_approval);
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

    $is_admin = $this->getBoolFromQuery($saved, 'isAdmin');
    $is_disabled = $this->getBoolFromQuery($saved, 'isDisabled');
    $is_system_agent = $this->getBoolFromQuery($saved, 'isSystemAgent');
    $is_mailing_list = $this->getBoolFromQuery($saved, 'isMailingList');
    $needs_approval = $this->getBoolFromQuery($saved, 'needsApproval');

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
        id(new AphrontFormSelectControl())
          ->setName('isAdmin')
          ->setLabel(pht('Administrators'))
          ->setValue($is_admin)
          ->setOptions(
            array(
              '' => pht('(Show All)'),
              'true' => pht('Show Only Administrators'),
              'false' => pht('Hide Administrators'),
            )))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setName('isDisabled')
          ->setLabel(pht('Disabled'))
          ->setValue($is_disabled)
          ->setOptions(
            array(
              '' => pht('(Show All)'),
              'true' => pht('Show Only Disabled Users'),
              'false' => pht('Hide Disabled Users'),
            )))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setName('isSystemAgent')
          ->setLabel(pht('Bots'))
          ->setValue($is_system_agent)
          ->setOptions(
            array(
              '' => pht('(Show All)'),
              'true' => pht('Show Only Bots'),
              'false' => pht('Hide Bots'),
            )))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setName('isMailingList')
          ->setLabel(pht('Mailing Lists'))
          ->setValue($is_mailing_list)
          ->setOptions(
            array(
              '' => pht('(Show All)'),
              'true' => pht('Show Only Mailing Lists'),
              'false' => pht('Hide Mailing Lists'),
            )))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setName('needsApproval')
          ->setLabel(pht('Needs Approval'))
          ->setValue($needs_approval)
          ->setOptions(
            array(
              '' => pht('(Show All)'),
              'true' => pht('Show Only Unapproved Users'),
              'false' => pht('Hide Unapproved Users'),
            )));

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
          ->setParameter('isDisabled', true);
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

    return $list;
  }

}
