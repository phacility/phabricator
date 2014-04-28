<?php

final class PhabricatorSlowvoteSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function buildSavedQueryFromRequest(AphrontRequest $request) {
    $saved = new PhabricatorSavedQuery();
    $saved->setParameter(
      'authorPHIDs',
      $this->readUsersFromRequest($request, 'authors'));

    $saved->setParameter('voted', $request->getBool('voted'));
    $saved->setParameter('statuses', $request->getArr('statuses'));

    return $saved;
  }

  public function buildQueryFromSavedQuery(PhabricatorSavedQuery $saved) {
    $query = id(new PhabricatorSlowvoteQuery())
      ->withAuthorPHIDs($saved->getParameter('authorPHIDs', array()));

    if ($saved->getParameter('voted')) {
      $query->withVotesByViewer(true);
    }

    $statuses = $saved->getParameter('statuses', array());
    if (count($statuses) == 1) {
      $status = head($statuses);
      if ($status == 'open') {
        $query->withIsClosed(false);
      } else {
        $query->withIsClosed(true);
      }
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
    $statuses = $saved_query->getParameter('statuses', array());

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
            $voted))
      ->appendChild(
        id(new AphrontFormCheckboxControl())
          ->setLabel(pht('Status'))
          ->addCheckbox(
            'statuses[]',
            'open',
            pht('Open'),
            in_array('open', $statuses))
          ->addCheckbox(
            'statuses[]',
            'closed',
            pht('Closed'),
            in_array('closed', $statuses)));
  }

  protected function getURI($path) {
    return '/vote/'.$path;
  }

  public function getBuiltinQueryNames() {
    $names = array(
      'open' => pht('Open Polls'),
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
      case 'open':
        return $query->setParameter('statuses', array('open'));
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
