<?php

final class PonderQuestionSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function buildSavedQueryFromRequest(AphrontRequest $request) {
    $saved = new PhabricatorSavedQuery();

    $saved->setParameter(
      'authorPHIDs',
      array_values($request->getArr('authors')));

    $saved->setParameter(
      'answererPHIDs',
      array_values($request->getArr('answerers')));

    return $saved;
  }

  public function buildQueryFromSavedQuery(PhabricatorSavedQuery $saved) {
    $query = id(new PonderQuestionQuery());

    $author_phids = $saved->getParameter('authorPHIDs');
    if ($author_phids) {
      $query->withAuthorPHIDs($author_phids);
    }

    $answerer_phids = $saved->getParameter('answererPHIDs');
    if ($answerer_phids) {
      $query->withAnswererPHIDs($answerer_phids);
    }

    return $query;
  }

  public function buildSearchForm(
    AphrontFormView $form,
    PhabricatorSavedQuery $saved_query) {

    $author_phids = $saved_query->getParameter('authorPHIDs', array());
    $answerer_phids = $saved_query->getParameter('answererPHIDs', array());

    $phids = array_merge($author_phids, $answerer_phids);
    $handles = id(new PhabricatorObjectHandleData($phids))
      ->setViewer($this->requireViewer())
      ->loadHandles();
    $tokens = mpull($handles, 'getFullName', 'getPHID');

    $author_tokens = array_select_keys($tokens, $author_phids);
    $answerer_tokens = array_select_keys($tokens, $answerer_phids);

    $form
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setDatasource('/typeahead/common/users/')
          ->setName('authors')
          ->setLabel(pht('Authors'))
          ->setValue($author_tokens))
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setDatasource('/typeahead/common/users/')
          ->setName('answerers')
          ->setLabel(pht('Answered By'))
          ->setValue($answerer_tokens));
  }

  protected function getURI($path) {
    return '/ponder/'.$path;
  }

  public function getBuiltinQueryNames() {
    $names = array(
      'all' => pht('All Questions'),
    );

    if ($this->requireViewer()->isLoggedIn()) {
      $names['authored'] = pht('Authored');
      $names['answered'] = pht('Answered');
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
      case 'answered':
        return $query->setParameter(
          'answererPHIDs',
          array($this->requireViewer()->getPHID()));
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
  }

}
