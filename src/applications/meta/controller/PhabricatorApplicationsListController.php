<?php

final class PhabricatorApplicationsListController
  extends PhabricatorApplicationsController
  implements PhabricatorApplicationSearchResultsControllerInterface {

  private $queryKey;

  public function willProcessRequest(array $data) {
    $this->queryKey = idx($data, 'queryKey');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $controller = id(new PhabricatorApplicationSearchController($request))
      ->setQueryKey($this->queryKey)
      ->setSearchEngine(new PhabricatorAppSearchEngine())
      ->setNavigation($this->buildSideNavView());

    return $this->delegateToController($controller);
  }

  public function renderResultsList(
    array $applications,
    PhabricatorSavedQuery $query) {
    assert_instances_of($applications, 'PhabricatorApplication');

    $list = new PHUIObjectItemListView();

    $applications = msort($applications, 'getName');

    foreach ($applications as $application) {
      $item = id(new PHUIObjectItemView())
        ->setHeader($application->getName())
        ->setHref('/applications/view/'.get_class($application).'/')
        ->addAttribute($application->getShortDescription());

      if (!$application->isInstalled()) {
        $item->addIcon('delete', pht('Uninstalled'));
      }

      if ($application->isBeta()) {
        $item->addIcon('lint-warning', pht('Beta'));
      }

      $list->addItem($item);
    }

    return $list;
   }

}
