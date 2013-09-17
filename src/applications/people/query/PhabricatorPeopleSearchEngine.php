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
    $saved->setParameter('createdStart', $request->getStr('createdStart'));
    $saved->setParameter('createdEnd', $request->getStr('createdEnd'));

    $this->readCustomFieldsFromRequest($request, $saved);

    return $saved;
  }

  public function buildQueryFromSavedQuery(PhabricatorSavedQuery $saved) {
    $query = id(new PhabricatorPeopleQuery())
      ->needPrimaryEmail(true);

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

    if ($is_admin) {
      $query->withIsAdmin(true);
    }

    if ($is_disabled) {
      $query->withIsDisabled(true);
    }

    if ($is_system_agent) {
      $query->withIsSystemAgent(true);
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
            pht('Show only Administrators.'),
            $is_admin)
          ->addCheckbox(
            'isDisabled',
            1,
            pht('Show only disabled users.'),
            $is_disabled)
          ->addCheckbox(
            'isSystemAgent',
            1,
            pht('Show only System Agents.'),
            $is_system_agent));

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

    return $names;
  }

  public function buildSavedQueryFromBuiltin($query_key) {
    $query = $this->newSavedQuery();
    $query->setQueryKey($query_key);

    switch ($query_key) {
      case 'all':
        return $query;
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
  }

}
