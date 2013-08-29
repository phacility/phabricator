<?php

final class PhabricatorCountdownSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function buildSavedQueryFromRequest(AphrontRequest $request) {
    $saved = new PhabricatorSavedQuery();
    $saved->setParameter(
      'authorPHIDs',
      $this->readUsersFromRequest($request, 'authors'));

    $saved->setParameter('upcoming', $request->getBool('upcoming'));

    return $saved;
  }

  public function buildQueryFromSavedQuery(PhabricatorSavedQuery $saved) {
    $query = id(new PhabricatorCountdownQuery());

    $author_phids = $saved->getParameter('authorPHIDs', array());
    if ($author_phids) {
      $query->withAuthorPHIDs($author_phids);
    }

    if ($saved->getParameter('upcoming')) {
      $query->withUpcoming(true);
    }

    return $query;
  }

  public function buildSearchForm(
    AphrontFormView $form,
    PhabricatorSavedQuery $saved_query) {
    $phids = $saved_query->getParameter('authorPHIDs', array());
    $handles = id(new PhabricatorObjectHandleData($phids))
      ->setViewer($this->requireViewer())
      ->loadHandles();
    $author_tokens = mpull($handles, 'getFullName', 'getPHID');

    $upcoming = $saved_query->getParameter('upcoming');

    $form
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setDatasource('/typeahead/common/users/')
          ->setName('authors')
          ->setLabel(pht('Authors'))
          ->setValue($author_tokens))
      ->appendChild(
        id(new AphrontFormCheckboxControl())
          ->addCheckbox(
            'upcoming',
            1,
            pht('Show only countdowns that are still counting down.'),
            $upcoming));

  }

  protected function getURI($path) {
    return '/countdown/'.$path;
  }

  public function getBuiltinQueryNames() {
    $names = array(
      'upcoming' => pht('Upcoming'),
      'all' => pht('All'),
    );

    if ($this->requireViewer()->getPHID()) {
      $names['authored'] = pht('Authored');
    }

    return $names;
  }

  public function buildSavedQueryFromBuiltin($query_key) {

    $query = $this->newSavedQuery();
    $query->setQueryKey($query_key);

    switch ($query_key) {
      case 'all':
        return $query;
      case 'authored':
        return $query->setParameter(
          'authorPHIDs',
          array($this->requireViewer()->getPHID()));
      case 'upcoming':
        return $query->setParameter('upcoming', true);
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
  }

}
