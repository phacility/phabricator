<?php

final class PhabricatorFeedListController extends PhabricatorFeedController
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
    $controller = id(new PhabricatorApplicationSearchController($request))
      ->setQueryKey($this->queryKey)
      ->setSearchEngine(new PhabricatorFeedSearchEngine())
      ->setNavigation($this->buildSideNavView());

    return $this->delegateToController($controller);
  }

  public function renderResultsList(
    array $feed,
    PhabricatorSavedQuery $query) {

    $builder = new PhabricatorFeedBuilder($feed);
    $builder->setShowHovercards(true);
    $builder->setUser($this->getRequest()->getUser());
    $view = $builder->buildView();

    return hsprintf(
      '<div class="phabricator-feed-frame">%s</div>',
      $view);
  }

}
