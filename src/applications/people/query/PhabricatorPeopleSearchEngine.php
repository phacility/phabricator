<?php

final class PhabricatorPeopleSearchEngine
  extends PhabricatorApplicationSearchEngine {

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

}
