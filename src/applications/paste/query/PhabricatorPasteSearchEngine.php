<?php

/**
 * @group paste
 */
final class PhabricatorPasteSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function buildSavedQueryFromRequest(AphrontRequest $request) {
    $saved = new PhabricatorSavedQuery();
    $saved->setParameter(
      'authorPHIDs',
      array_values($request->getArr('authors')));

    $languages = $request->getStrList('languages');
    if ($request->getBool('noLanguage')) {
      $languages[] = null;
    }
    $saved->setParameter('languages', $languages);

    $saved->setParameter('createdStart', $request->getStr('createdStart'));
    $saved->setParameter('createdEnd', $request->getStr('createdEnd'));

    return $saved;
  }

  public function buildQueryFromSavedQuery(PhabricatorSavedQuery $saved) {
    $query = id(new PhabricatorPasteQuery())
      ->needContent(true)
      ->withAuthorPHIDs($saved->getParameter('authorPHIDs', array()))
      ->withLanguages($saved->getParameter('languages', array()));

    $start = $this->parseDateTime($saved->getParameter('createdStart'));
    $end = $this->parseDateTime($saved->getParameter('createdEnd'));

    if ($start) {
      $query->withDateCreatedAfter($start);
    }

    if ($end) {
      $query->withDateCreatedBefore($end);
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

    $languages = $saved_query->getParameter('languages', array());
    $no_language = false;
    foreach ($languages as $key => $language) {
      if ($language === null) {
        $no_language = true;
        unset($languages[$key]);
        continue;
      }
    }

    $form
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setDatasource('/typeahead/common/users/')
          ->setName('authors')
          ->setLabel(pht('Authors'))
          ->setValue($author_tokens))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setName('languages')
          ->setLabel(pht('Languages'))
          ->setValue(implode(', ', $languages)))
      ->appendChild(
        id(new AphrontFormCheckboxControl())
          ->addCheckbox(
            'noLanguage',
            1,
            pht('Find Pastes with no specified language.'),
            $no_language));

    $this->buildDateRange(
      $form,
      $saved_query,
      'createdStart',
      pht('Created After'),
      'createdEnd',
      pht('Created Before'));

  }

  protected function getURI($path) {
    return '/paste/'.$path;
  }

  public function getBuiltinQueryNames() {
    $names = array(
      'all' => pht('All Pastes'),
    );

    if ($this->requireViewer()->isLoggedIn()) {
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
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
  }

}
