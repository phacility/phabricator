<?php

final class PhabricatorPasteSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Pastes');
  }

  public function getApplicationClassName() {
    return 'PhabricatorPasteApplication';
  }

  public function buildSavedQueryFromRequest(AphrontRequest $request) {
    $saved = new PhabricatorSavedQuery();
    $saved->setParameter(
      'authorPHIDs',
      $this->readUsersFromRequest($request, 'authors'));

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
    $author_phids = $saved_query->getParameter('authorPHIDs', array());

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
      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setDatasource(new PhabricatorPeopleDatasource())
          ->setName('authors')
          ->setLabel(pht('Authors'))
          ->setValue($author_phids))
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

  protected function getBuiltinQueryNames() {
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

  protected function getRequiredHandlePHIDsForResultList(
    array $pastes,
    PhabricatorSavedQuery $query) {
    return mpull($pastes, 'getAuthorPHID');
  }

  protected function renderResultList(
    array $pastes,
    PhabricatorSavedQuery $query,
    array $handles) {
    assert_instances_of($pastes, 'PhabricatorPaste');

    $viewer = $this->requireViewer();

    $lang_map = PhabricatorEnv::getEnvConfig('pygments.dropdown-choices');

    $list = new PHUIObjectItemListView();
    $list->setUser($viewer);
    foreach ($pastes as $paste) {
      $created = phabricator_date($paste->getDateCreated(), $viewer);
      $author = $handles[$paste->getAuthorPHID()]->renderLink();

      $lines = phutil_split_lines($paste->getContent());

      $preview = id(new PhabricatorSourceCodeView())
        ->setLimit(5)
        ->setLines($lines)
        ->setURI(new PhutilURI($paste->getURI()));

      $source_code = phutil_tag(
        'div',
        array(
          'class' => 'phabricator-source-code-summary',
        ),
        $preview);

      $created = phabricator_datetime($paste->getDateCreated(), $viewer);
      $line_count = count($lines);
      $line_count = pht(
        '%s Line(s)',
        new PhutilNumber($line_count));

      $title = nonempty($paste->getTitle(), pht('(An Untitled Masterwork)'));

      $item = id(new PHUIObjectItemView())
        ->setObjectName('P'.$paste->getID())
        ->setHeader($title)
        ->setHref('/P'.$paste->getID())
        ->setObject($paste)
        ->addByline(pht('Author: %s', $author))
        ->addIcon('none', $created)
        ->addIcon('none', $line_count)
        ->appendChild($source_code);

      $lang_name = $paste->getLanguage();
      if ($lang_name) {
        $lang_name = idx($lang_map, $lang_name, $lang_name);
        $item->addIcon('none', $lang_name);
      }

      $list->addItem($item);
    }

    return $list;
  }
}
