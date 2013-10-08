<?php

final class PhabricatorSlowvoteSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function buildSavedQueryFromRequest(AphrontRequest $request) {
    $saved = new PhabricatorSavedQuery();
    $saved->setParameter(
      'authorPHIDs',
      $this->readUsersFromRequest($request, 'authors'));

    $saved->setParameter('voted', $request->getBool('voted'));

    return $saved;
  }

  public function buildQueryFromSavedQuery(PhabricatorSavedQuery $saved) {
    $query = id(new PhabricatorSlowvoteQuery())
      ->withAuthorPHIDs($saved->getParameter('authorPHIDs', array()));

    if ($saved->getParameter('voted')) {
      $query->withVotesByViewer(true);
    }

    return $query;
  }

  public function buildSearchForm(
    AphrontFormView $form,
    PhabricatorSavedQuery $saved_query) {
    $phids = $saved_query->getParameter('authorPHIDs', array());
    $author_handles = id(new PhabricatorHandleQuery())
      ->setViewer($this->requireViewer())
      ->withPHIDs($phids)
      ->execute();

    $voted = $saved_query->getParameter('voted', false);

    $form
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setDatasource('/typeahead/common/users/')
          ->setName('authors')
          ->setLabel(pht('Authors'))
          ->setValue($author_handles))
      ->appendChild(
        id(new AphrontFormCheckboxControl())
          ->addCheckbox(
            'voted',
            1,
            pht("Show only polls I've voted in."),
            $voted));
  }

  protected function getURI($path) {
    return '/vote/'.$path;
  }

  public function getBuiltinQueryNames() {
    $names = array(
      'all' => pht('All Polls'),
    );

    if ($this->requireViewer()->isLoggedIn()) {
      $names['authored'] = pht('Authored');
      $names['voted'] = pht('Voted In');
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
      case 'voted':
        return $query->setParameter('voted', true);
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
  }

}
