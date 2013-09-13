<?php

final class PhabricatorApplicationSearchController
  extends PhabricatorSearchBaseController {

  private $searchEngine;
  private $navigation;
  private $queryKey;
  private $preface;

  public function setPreface($preface) {
    $this->preface = $preface;
    return $this;
  }

  public function getPreface() {
    return $this->preface;
  }

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
    } else if (!strlen($this->queryKey)) {
      if ($request->isHTTPGet() && $request->getPassthroughRequestData()) {
        // If this is a GET request and it has some query data, don't
        // do anything. We'll build and execute a query from it below.
        // This allows external tools to build URIs like "/query/?users=a,b".
      } else {
        // Otherwise, there's no query data so just run the user's default
        // query for this application.
        $query_key = head_key($engine->loadEnabledNamedQueries());
      }
    }

    if ($engine->isBuiltinQuery($query_key)) {
      $saved_query = $engine->buildSavedQueryFromBuiltin($query_key);
      $named_query = idx($engine->loadEnabledNamedQueries(), $query_key);
    } else if ($query_key) {
      $saved_query = id(new PhabricatorSavedQueryQuery())
        ->setViewer($user)
        ->withQueryKeys(array($query_key))
        ->executeOne();

      if (!$saved_query) {
        return new Aphront404Response();
      }

      $named_query = idx($engine->loadEnabledNamedQueries(), $query_key);
    } else {
      $saved_query = $engine->buildSavedQueryFromRequest($request);
    }

    $nav->selectFilter(
      'query/'.$saved_query->getQueryKey(),
      'query/advanced');

    $form = id(new AphrontFormView())
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

    if ($this->getPreface()) {
      $nav->appendChild($this->getPreface());
    }

    $nav->appendChild($filter_view);

    if ($run_query) {
      $query = $engine->buildQueryFromSavedQuery($saved_query);

      $pager = new AphrontCursorPagerView();
      $pager->readFromRequest($request);
      $pager->setPageSize($engine->getPageSize($saved_query));
      $objects = $query->setViewer($request->getUser())
        ->executeWithCursorPager($pager);

      $list = $parent->renderResultsList($objects, $saved_query);

      $nav->appendChild($list);

      // TODO: This is a bit hacky.
      if ($list instanceof PHUIObjectItemListView) {
        $list->setNoDataString(pht("No results found for this query."));
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
      ));
  }

  private function processEditRequest() {
    $parent = $this->getDelegatingController();
    $request = $this->getRequest();
    $user = $request->getUser();
    $engine = $this->getSearchEngine();
    $nav = $this->getNavigation();

    $named_queries = $engine->loadAllNamedQueries();

    $list_id = celerity_generate_unique_node_id();

    $list = new PHUIObjectItemListView();
    $list->setUser($user);
    $list->setID($list_id);

    Javelin::initBehavior(
      'search-reorder-queries',
      array(
        'listID' => $list_id,
        'orderURI' => '/search/order/'.get_class($engine).'/',
      ));

    foreach ($named_queries as $named_query) {
      $class = get_class($engine);
      $key = $named_query->getQueryKey();

      $item = id(new PHUIObjectItemView())
        ->setHeader($named_query->getQueryName())
        ->setHref($engine->getQueryResultsPageURI($key));

      if ($named_query->getIsBuiltin() && $named_query->getIsDisabled()) {
        $icon = 'new';
      } else {
        $icon = 'delete';
      }

      $item->addAction(
        id(new PHUIListItemView())
          ->setIcon($icon)
          ->setHref('/search/delete/'.$key.'/'.$class.'/')
          ->setWorkflow(true));

      if ($named_query->getIsBuiltin()) {
        if ($named_query->getIsDisabled()) {
          $item->addIcon('delete-grey', pht('Disabled'));
          $item->setDisabled(true);
        } else {
          $item->addIcon('lock-grey', pht('Builtin'));
        }
      } else {
        $item->addAction(
          id(new PHUIListItemView())
            ->setIcon('edit')
            ->setHref('/search/edit/'.$key.'/'));
      }

      $item->setGrippable(true);
      $item->addSigil('named-query');
      $item->setMetadata(
        array(
          'queryKey' => $named_query->getQueryKey(),
        ));

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
