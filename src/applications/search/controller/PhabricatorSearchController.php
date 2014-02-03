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

    $controller = id(new PhabricatorApplicationSearchController($request))
      ->setQueryKey($this->queryKey)
      ->setSearchEngine(new PhabricatorSearchApplicationSearchEngine())
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
      ->setShadow(true)
      ->appendChild($results)
      ->addClass('phabricator-search-result-box');
  }

}
