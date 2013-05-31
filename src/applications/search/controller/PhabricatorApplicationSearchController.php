<?php

final class PhabricatorApplicationSearchController
  extends PhabricatorSearchBaseController {

  private $searchEngine;
  private $navigation;
  private $queryKey;

  public function setQueryKey($query_key) {
    $this->queryKey = $query_key;
    return $this;
  }

  protected function getQueryKey() {
    return $this->queryKey;
  }

  public function setNavigation(AphrontSideNavFilterView $navigation) {
    $this->navigation = $navigation;
    return $this;
  }

  protected function getNavigation() {
    return $this->navigation;
  }

  public function setSearchEngine(
    PhabricatorApplicationSearchEngine $search_engine) {
    $this->searchEngine = $search_engine;
    return $this;
  }

  protected function getSearchEngine() {
    return $this->searchEngine;
  }

  protected function validateDelegatingController() {
    $parent = $this->getDelegatingController();

    if (!$parent) {
      throw new Exception(
        "You must delegate to this controller, not invoke it directly.");
    }

    $engine = $this->getSearchEngine();
    if (!$engine) {
      throw new Exception(
        "Call setEngine() before delegating to this controller!");
    }

    $nav = $this->getNavigation();
    if (!$nav) {
      throw new Exception(
        "Call setNavigation() before delegating to this controller!");
    }

    $engine->setViewer($this->getRequest()->getUser());

    $parent = $this->getDelegatingController();
    $interface = 'PhabricatorApplicationSearchResultsControllerInterface';
    if (!$parent instanceof $interface) {
      throw new Exception(
        "Delegating controller must implement '{$interface}'.");
    }
  }

  public function processRequest() {
    $this->validateDelegatingController();

    $key = $this->getQueryKey();
    if ($key == 'edit') {
      return $this->processEditRequest();
    } else {
      return $this->processSearchRequest();
    }
  }

  private function processSearchRequest() {
    $parent = $this->getDelegatingController();
    $request = $this->getRequest();
    $user = $request->getUser();
    $engine = $this->getSearchEngine();
    $nav = $this->getNavigation();

    if ($request->isFormPost()) {
      $saved_query = $engine->buildSavedQueryFromRequest($request);
      $this->saveQuery($saved_query);
      return id(new AphrontRedirectResponse())->setURI(
        $engine->getQueryResultsPageURI($saved_query->getQueryKey()));
    }

    $named_query = null;
    $run_query = true;
    $query_key = $this->queryKey;
    if ($this->queryKey == 'advanced') {
      $run_query = false;
      $query_key = $request->getStr('query');
    }

    if ($engine->isBuiltinQuery($query_key)) {
      $saved_query = $engine->buildSavedQueryFromBuiltin($query_key);
      $named_query = $engine->getBuiltinQuery($query_key);
    } else if ($query_key) {
      $saved_query = id(new PhabricatorSavedQueryQuery())
        ->setViewer($user)
        ->withQueryKeys(array($query_key))
        ->executeOne();

      if (!$saved_query) {
        return new Aphront404Response();
      }

      $named_query = id(new PhabricatorNamedQueryQuery())
        ->setViewer($user)
        ->withQueryKeys(array($saved_query->getQueryKey()))
        ->withEngineClassNames(array(get_class($engine)))
        ->withUserPHIDs(array($user->getPHID()))
        ->executeOne();
    } else {
      $saved_query = $engine->buildSavedQueryFromRequest($request);
    }

    $nav->selectFilter(
      'query/'.$saved_query->getQueryKey(),
      'query/advanced');

    $form = id(new AphrontFormView())
      ->setNoShading(true)
      ->setUser($user);

    $engine->buildSearchForm($form, $saved_query);

    $errors = $engine->getErrors();
    if ($errors) {
      $run_query = false;
      $errors = id(new AphrontErrorView())
        ->setTitle(pht('Query Errors'))
        ->setErrors($errors);
    }

    $submit = id(new AphrontFormSubmitControl())
      ->setValue(pht('Execute Query'));

    if ($run_query && !$named_query && $user->isLoggedIn()) {
      $submit->addCancelButton(
        '/search/edit/'.$saved_query->getQueryKey().'/',
        pht('Save Custom Query...'));
    }

    $form->appendChild($submit);
    $filter_view = id(new AphrontListFilterView())->appendChild($form);

    if ($run_query && $named_query) {
      if ($named_query->getIsBuiltin()) {
        $description = pht(
          'Showing results for query "%s".',
          $named_query->getQueryName());
      } else {
        $description = pht(
          'Showing results for saved query "%s".',
          $named_query->getQueryName());
      }

      $filter_view->setCollapsed(
        pht('Edit Query...'),
        pht('Hide Query'),
        $description,
        $this->getApplicationURI('query/advanced/?query='.$query_key));
    }

    $nav->appendChild($filter_view);

    if ($run_query) {
      $query = $engine->buildQueryFromSavedQuery($saved_query);

      $pager = new AphrontCursorPagerView();
      $pager->readFromRequest($request);
      $objects = $query->setViewer($request->getUser())
        ->executeWithCursorPager($pager);

      $list = $parent->renderResultsList($objects);
      $list->setNoDataString(pht("No results found for this query."));

      $nav->appendChild($list);

      // TODO: This is a bit hacky.
      if ($list instanceof PhabricatorObjectItemListView) {
        $list->setPager($pager);
      } else {
        $nav->appendChild($pager);
      }
    }

    if ($errors) {
      $nav->appendChild($errors);
    }

    if ($named_query) {
      $title = pht('Query: %s', $named_query->getQueryName());
    } else {
      $title = pht('Advanced Search');
    }

    $crumbs = $parent
      ->buildApplicationCrumbs()
      ->addCrumb(
        id(new PhabricatorCrumbView())
          ->setName(pht("Search")));

    $nav->setCrumbs($crumbs);

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => $title,
        'device' => true,
        'dust' => true,
      ));
  }

  private function processEditRequest() {
    $parent = $this->getDelegatingController();
    $request = $this->getRequest();
    $user = $request->getUser();
    $engine = $this->getSearchEngine();
    $nav = $this->getNavigation();

    $named_queries = id(new PhabricatorNamedQueryQuery())
      ->setViewer($user)
      ->withUserPHIDs(array($user->getPHID()))
      ->withEngineClassNames(array(get_class($engine)))
      ->execute();

    $named_queries += $engine->getBuiltinQueries();

    $list = new PhabricatorObjectItemListView();
    $list->setUser($user);

    foreach ($named_queries as $named_query) {
      $date_created = phabricator_datetime(
        $named_query->getDateCreated(),
        $user);

      $item = id(new PhabricatorObjectItemView())
        ->setHeader($named_query->getQueryName())
        ->setHref($engine->getQueryResultsPageURI($named_query->getQueryKey()));

      if ($named_query->getIsBuiltin()) {
        $item->addIcon('lock-grey', pht('Builtin'));
        $item->setBarColor('grey');
      } else {
        $item->addIcon('none', $date_created);
        $item->addAction(
          id(new PhabricatorMenuItemView())
            ->setIcon('delete')
            ->setHref('/search/delete/'.$named_query->getQueryKey().'/')
            ->setWorkflow(true));
        $item->addAction(
          id(new PhabricatorMenuItemView())
            ->setIcon('edit')
            ->setHref('/search/edit/'.$named_query->getQueryKey().'/'));
      }

      $list->addItem($item);
    }

    $list->setNoDataString(pht('No saved queries.'));

    $crumbs = $parent
      ->buildApplicationCrumbs()
      ->addCrumb(
        id(new PhabricatorCrumbView())
          ->setName(pht("Saved Queries"))
          ->setHref($engine->getQueryManagementURI()));

    $nav->selectFilter('query/edit');
    $nav->setCrumbs($crumbs);
    $nav->appendChild($list);

    return $parent->buildApplicationPage(
      $nav,
      array(
        'title' => pht("Saved Queries"),
        'device' => true,
        'dust' => true,
      ));
  }

  private function saveQuery(PhabricatorSavedQuery $query) {
    $query->setEngineClassName(get_class($this->getSearchEngine()));

    $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
    try {
      $query->save();
    } catch (AphrontQueryDuplicateKeyException $ex) {
      // Ignore, this is just a repeated search.
    }
    unset($unguarded);
  }

  protected function buildApplicationMenu() {
    return $this->getDelegatingController()->buildApplicationMenu();
  }

}
