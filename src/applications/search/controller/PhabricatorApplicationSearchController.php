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
        pht('You must delegate to this controller, not invoke it directly.'));
    }

    $engine = $this->getSearchEngine();
    if (!$engine) {
      throw new PhutilInvalidStateException('setEngine');
    }

    $nav = $this->getNavigation();
    if (!$nav) {
      throw new PhutilInvalidStateException('setNavigation');
    }

    $engine->setViewer($this->getRequest()->getUser());

    $parent = $this->getDelegatingController();
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
      $engine->saveQuery($saved_query);
      return id(new AphrontRedirectResponse())->setURI(
        $engine->getQueryResultsPageURI($saved_query->getQueryKey()).'#R');
    }

    $named_query = null;
    $run_query = true;
    $query_key = $this->queryKey;
    if ($this->queryKey == 'advanced') {
      $run_query = false;
      $query_key = $request->getStr('query');
    } else if (!strlen($this->queryKey)) {
      $found_query_data = false;

      if ($request->isHTTPGet()) {
        // If this is a GET request and it has some query data, don't
        // do anything unless it's only before= or after=. We'll build and
        // execute a query from it below. This allows external tools to build
        // URIs like "/query/?users=a,b".
        $pt_data = $request->getPassthroughRequestData();

        foreach ($pt_data as $pt_key => $pt_value) {
          if ($pt_key != 'before' && $pt_key != 'after') {
            $found_query_data = true;
            break;
          }
        }
      }

      if (!$found_query_data) {
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

      // Save the query to generate a query key, so "Save Custom Query..." and
      // other features like Maniphest's "Export..." work correctly.
      $engine->saveQuery($saved_query);
    }

    $nav->selectFilter(
      'query/'.$saved_query->getQueryKey(),
      'query/advanced');

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->setAction($request->getPath());

    $engine->buildSearchForm($form, $saved_query);

    $errors = $engine->getErrors();
    if ($errors) {
      $run_query = false;
    }

    $submit = id(new AphrontFormSubmitControl())
      ->setValue(pht('Execute Query'));

    if ($run_query && !$named_query && $user->isLoggedIn()) {
      $submit->addCancelButton(
        '/search/edit/'.$saved_query->getQueryKey().'/',
        pht('Save Custom Query...'));
    }

    // TODO: A "Create Dashboard Panel" action goes here somewhere once
    // we sort out T5307.

    $form->appendChild($submit);

    if ($this->getPreface()) {
      $nav->appendChild($this->getPreface());
    }

    if ($named_query) {
      $title = $named_query->getQueryName();
    } else {
      $title = pht('Advanced Search');
    }

    $header = id(new PHUIHeaderView())
      ->setHeader($title);

    $box = id(new PHUIObjectBoxView())
      ->setHeader($header);

    if ($run_query || $named_query) {
      $box->setShowHide(
        pht('Edit Query'),
        pht('Hide Query'),
        $form,
        $this->getApplicationURI('query/advanced/?query='.$query_key),
        (!$named_query ? true : false));
    } else {
      $box->setForm($form);
    }

    $nav->appendChild($box);

    if ($run_query) {
      $box->setAnchor(
        id(new PhabricatorAnchorView())
          ->setAnchorName('R'));

      try {
        $query = $engine->buildQueryFromSavedQuery($saved_query);

        $pager = $engine->newPagerForSavedQuery($saved_query);
        $pager->readFromRequest($request);

        $objects = $engine->executeQuery($query, $pager);

        $engine->setRequest($request);
        $list = $engine->renderResults($objects, $saved_query);

        if (!($list instanceof PhabricatorApplicationSearchResultView)) {
          throw new Exception(
            pht(
              'SearchEngines must render a "%s" object, but this engine '.
              '(of class "%s") rendered something else.',
              'PhabricatorApplicationSearchResultView',
              get_class($engine)));
        }

        if ($list->getActions()) {
          foreach ($list->getActions() as $action) {
            $header->addActionLink($action);
          }
        }

        if ($list->getObjectList()) {
          $box->setObjectList($list->getObjectList());
        }
        if ($list->getTable()) {
          $box->setTable($list->getTable());
        }
        if ($list->getInfoView()) {
          $box->setInfoView($list->getInfoView());
        }
        if ($list->getContent()) {
          $box->appendChild($list->getContent());
        }
        if ($list->getCollapsed()) {
          $box->setCollapsed(true);
        }

        if ($pager->willShowPagingControls()) {
          $pager_box = id(new PHUIBoxView())
            ->addPadding(PHUI::PADDING_MEDIUM)
            ->addMargin(PHUI::MARGIN_LARGE)
            ->setBorder(true)
            ->appendChild($pager);
          $nav->appendChild($pager_box);
        }

      } catch (PhabricatorTypeaheadInvalidTokenException $ex) {
        $errors[] = pht(
          'This query specifies an invalid parameter. Review the '.
          'query parameters and correct errors.');
      }
    }

    if ($errors) {
      $box->setFormErrors($errors, pht('Query Errors'));
    }

    $crumbs = $parent
      ->buildApplicationCrumbs()
      ->addTextCrumb($title);

    $nav->setCrumbs($crumbs);

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => pht('Query: %s', $title),
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
        $icon = 'fa-plus';
      } else {
        $icon = 'fa-times';
      }

      $item->addAction(
        id(new PHUIListItemView())
          ->setIcon($icon)
          ->setHref('/search/delete/'.$key.'/'.$class.'/')
          ->setWorkflow(true));

      if ($named_query->getIsBuiltin()) {
        if ($named_query->getIsDisabled()) {
          $item->addIcon('fa-times lightgreytext', pht('Disabled'));
          $item->setDisabled(true);
        } else {
          $item->addIcon('fa-lock lightgreytext', pht('Builtin'));
        }
      } else {
        $item->addAction(
          id(new PHUIListItemView())
            ->setIcon('fa-pencil')
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
      ->addTextCrumb(pht('Saved Queries'), $engine->getQueryManagementURI());

    $nav->selectFilter('query/edit');
    $nav->setCrumbs($crumbs);

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Saved Queries'))
      ->setObjectList($list);

    $nav->appendChild($box);

    return $parent->buildApplicationPage(
      $nav,
      array(
        'title' => pht('Saved Queries'),
      ));
  }

  public function buildApplicationMenu() {
    return $this->getDelegatingController()->buildApplicationMenu();
  }

}
