<?php

final class ManiphestTaskSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function buildSavedQueryFromRequest(AphrontRequest $request) {
    $saved = new PhabricatorSavedQuery();

    $saved->setParameter(
      'assignedPHIDs',
      $this->readUsersFromRequest($request, 'assigned'));

    $saved->setParameter('withUnassigned', $request->getBool('withUnassigned'));

    $saved->setParameter(
      'authorPHIDs',
      $this->readUsersFromRequest($request, 'authors'));

    return $saved;
  }

  public function buildQueryFromSavedQuery(PhabricatorSavedQuery $saved) {
    $query = id(new ManiphestTaskQuery());

    $author_phids = $saved->getParameter('authorPHIDs');
    if ($author_phids) {
      $query->withAuthors($author_phids);
    }

    $with_unassigned = $saved->getParameter('withUnassigned');
    if ($with_unassigned) {
      $query->withOwners(array(null));
    } else {
      $assigned_phids = $saved->getParameter('assignedPHIDs', array());
      if ($assigned_phids) {
        $query->withOwners($assigned_phids);
      }
    }

    return $query;
  }

  public function buildSearchForm(
    AphrontFormView $form,
    PhabricatorSavedQuery $saved) {

    $assigned_phids = $saved->getParameter('assignedPHIDs', array());
    $author_phids = $saved->getParameter('authorPHIDs', array());

    $all_phids = array_merge($assigned_phids, $author_phids);

    if ($all_phids) {
      $handles = id(new PhabricatorHandleQuery())
        ->setViewer($this->requireViewer())
        ->withPHIDs($all_phids)
        ->execute();
    } else {
      $handles = array();
    }

    $assigned_tokens = array_select_keys($handles, $assigned_phids);
    $assigned_tokens = mpull($assigned_tokens, 'getFullName', 'getPHID');

    $author_tokens = array_select_keys($handles, $author_phids);
    $author_tokens = mpull($author_tokens, 'getFullName', 'getPHID');

    $with_unassigned = $saved->getParameter('withUnassigned');

    $form
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setDatasource('/typeahead/common/accounts/')
          ->setName('assigned')
          ->setLabel(pht('Assigned To'))
          ->setValue($assigned_tokens))
      ->appendChild(
        id(new AphrontFormCheckboxControl())
          ->addCheckbox(
            'withUnassigned',
            1,
            pht('Show only unassigned tasks.'),
            $with_unassigned))
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setDatasource('/typeahead/common/accounts/')
          ->setName('authors')
          ->setLabel(pht('Authors'))
          ->setValue($author_tokens));
  }

  protected function getURI($path) {
    return '/maniphest/'.$path;
  }

  public function getBuiltinQueryNames() {
    $names = array();

    if ($this->requireViewer()->isLoggedIn()) {
      $names['assigned'] = pht('Assigned');
      $names['authored'] = pht('Authored');
    }

    $names['all'] = pht('All Tasks');

    return $names;
  }

  public function buildSavedQueryFromBuiltin($query_key) {

    $query = $this->newSavedQuery();
    $query->setQueryKey($query_key);

    $viewer_phid = $this->requireViewer()->getPHID();

    switch ($query_key) {
      case 'all':
        return $query;
      case 'assigned':
        return $query->setParameter('assignedPHIDs', array($viewer_phid));
      case 'authored':
        return $query->setParameter('authorPHIDs', array($viewer_phid));
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
  }

}
