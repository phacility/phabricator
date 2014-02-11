<?php

final class PhabricatorSearchController
  extends PhabricatorSearchBaseController
  implements PhabricatorApplicationSearchResultsControllerInterface {

  private $queryKey;

  public function shouldAllowPublic() {
    return true;
  }

  public function willProcessRequest(array $data) {
    $this->queryKey = idx($data, 'queryKey');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    if ($request->getStr('jump') != 'no') {
      $pref_jump = PhabricatorUserPreferences::PREFERENCE_SEARCHBAR_JUMP;
      if ($viewer->loadPreferences($pref_jump, 1)) {
        $response = PhabricatorJumpNavHandler::getJumpResponse(
          $viewer,
          $request->getStr('query'));
        if ($response) {
          return $response;
        }
      }
    }

    $engine = new PhabricatorSearchApplicationSearchEngine();
    $engine->setViewer($viewer);

    // NOTE: This is a little weird. If we're coming from primary search, we
    // load the user's first search filter and overwrite the "query" part of
    // it, then send them to that result page. This is sort of odd, but lets
    // users choose a default query like "Open Tasks" in a reasonable way,
    // with only this piece of somewhat-sketchy code. See discussion in T4365.

    if ($request->getBool('search:primary')) {
      $named_queries = $engine->loadEnabledNamedQueries();
      if ($named_queries) {
        $named = head($named_queries);

        $query_key = $named->getQueryKey();
        $saved = null;
        if ($engine->isBuiltinQuery($query_key)) {
          $saved = $engine->buildSavedQueryFromBuiltin($query_key);
        } else {
          $saved = id(new PhabricatorSavedQueryQuery())
            ->setViewer($viewer)
            ->withQueryKeys(array($query_key))
            ->executeOne();
        }

        if ($saved) {
          $saved->setParameter('query', $request->getStr('query'));
          $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
            try {
              $saved->setID(null)->save();
            } catch (AphrontQueryDuplicateKeyException $ex) {
              // Ignore, this is just a repeated search.
            }
          unset($unguarded);

          $results_uri = $engine->getQueryResultsPageURI(
            $saved->getQueryKey()).'#R';

          return id(new AphrontRedirectResponse())->setURI($results_uri);
        }
      }
    }

    $controller = id(new PhabricatorApplicationSearchController($request))
      ->setQueryKey($this->queryKey)
      ->setSearchEngine($engine)
      ->setUseOffsetPaging(true)
      ->setNavigation($this->buildSideNavView());

    return $this->delegateToController($controller);
  }

  public function buildSideNavView($for_app = false) {
    $viewer = $this->getRequest()->getUser();

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    id(new PhabricatorSearchApplicationSearchEngine())
      ->setViewer($viewer)
      ->addNavigationItems($nav->getMenu());

    $nav->selectFilter(null);

    return $nav;
  }

  public function renderResultsList(
    array $results,
    PhabricatorSavedQuery $query) {

    $viewer = $this->getRequest()->getUser();

    if ($results) {
      $objects = id(new PhabricatorObjectQuery())
        ->setViewer($viewer)
        ->withPHIDs(mpull($results, 'getPHID'))
        ->execute();

      $output = array();
      foreach ($results as $phid => $handle) {
        $view = id(new PhabricatorSearchResultView())
          ->setHandle($handle)
          ->setQuery($query)
          ->setObject(idx($objects, $phid));
        $output[] = $view->render();
      }

      $results = phutil_tag_div(
        'phabricator-search-result-list',
        $output);
    } else {
      $results = phutil_tag_div(
        'phabricator-search-result-list',
        phutil_tag(
          'p',
          array('class' => 'phabricator-search-no-results'),
          pht('No search results.')));
    }

    return id(new PHUIBoxView())
      ->addMargin(PHUI::MARGIN_LARGE)
      ->addPadding(PHUI::PADDING_LARGE)
      ->setBorder(true)
      ->appendChild($results)
      ->addClass('phabricator-search-result-box');
  }

}
