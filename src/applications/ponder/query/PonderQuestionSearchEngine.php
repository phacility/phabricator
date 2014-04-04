<?php

final class PonderQuestionSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function buildSavedQueryFromRequest(AphrontRequest $request) {
    $saved = new PhabricatorSavedQuery();

    $saved->setParameter(
      'authorPHIDs',
      $this->readUsersFromRequest($request, 'authors'));

    $saved->setParameter(
      'answererPHIDs',
      $this->readUsersFromRequest($request, 'answerers'));

    $saved->setParameter('status', $request->getStr('status'));

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

    $status = $saved->getParameter('status');
    if ($status != null) {
      switch ($status) {
        case 0:
          $query->withStatus(PonderQuestionQuery::STATUS_OPEN);
          break;
        case 1:
          $query->withStatus(PonderQuestionQuery::STATUS_CLOSED);
          break;
      }
    }

    return $query;
  }

  public function buildSearchForm(
    AphrontFormView $form,
    PhabricatorSavedQuery $saved_query) {

    $author_phids = $saved_query->getParameter('authorPHIDs', array());
    $answerer_phids = $saved_query->getParameter('answererPHIDs', array());
    $status = $saved_query->getParameter(
      'status', PonderQuestionStatus::STATUS_OPEN);

    $phids = array_merge($author_phids, $answerer_phids);
    $handles = id(new PhabricatorHandleQuery())
      ->setViewer($this->requireViewer())
      ->withPHIDs($phids)
      ->execute();

    $form
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setDatasource('/typeahead/common/users/')
          ->setName('authors')
          ->setLabel(pht('Authors'))
          ->setValue(array_select_keys($handles, $author_phids)))
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setDatasource('/typeahead/common/users/')
          ->setName('answerers')
          ->setLabel(pht('Answered By'))
          ->setValue(array_select_keys($handles, $answerer_phids)))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel(pht('Status'))
          ->setName('status')
          ->setValue($status)
          ->setOptions(PonderQuestionStatus::getQuestionStatusMap()));
  }

  protected function getURI($path) {
    return '/ponder/'.$path;
  }

  public function getBuiltinQueryNames() {
    $names = array(
      'open' => pht('Open Questions'),
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
      case 'open':
        return $query->setParameter('status', PonderQuestionQuery::STATUS_OPEN);
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
