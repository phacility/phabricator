<?php

final class HarbormasterPlanListController
  extends HarbormasterPlanController
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
      ->setSearchEngine(new HarbormasterBuildPlanSearchEngine())
      ->setNavigation($this->buildSideNavView());

    return $this->delegateToController($controller);
  }

  public function renderResultsList(
    array $plans,
    PhabricatorSavedQuery $query) {
    assert_instances_of($plans, 'HarbormasterBuildPlan');

    $viewer = $this->getRequest()->getUser();

    $list = new PHUIObjectItemListView();
    foreach ($plans as $plan) {
      $id = $plan->getID();

      $item = id(new PHUIObjectItemView())
        ->setObjectName(pht('Plan %d', $plan->getID()))
        ->setHeader($plan->getName());

      $item->setHref($this->getApplicationURI("plan/{$id}/"));

      $list->addItem($item);
    }

    return $list;
  }

  public function buildSideNavView($for_app = false) {
    $user = $this->getRequest()->getUser();

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    id(new HarbormasterBuildPlanSearchEngine())
      ->setViewer($user)
      ->addNavigationItems($nav->getMenu());

    $nav->selectFilter(null);

    return $nav;
  }

  public function buildApplicationMenu() {
    return $this->buildSideNavView(true)->getMenu();
  }

}
