<?php

final class HarbormasterBuildableListController
  extends HarbormasterController
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
      ->setSearchEngine(new HarbormasterBuildableSearchEngine())
      ->setNavigation($this->buildSideNavView());

    return $this->delegateToController($controller);
  }

  public function renderResultsList(
    array $buildables,
    PhabricatorSavedQuery $query) {
    assert_instances_of($buildables, 'HarbormasterBuildable');

    $viewer = $this->getRequest()->getUser();

    $list = new PHUIObjectItemListView();
    foreach ($buildables as $buildable) {
      $id = $buildable->getID();

      $item = id(new PHUIObjectItemView())
        ->setHeader(pht('Buildable %d', $buildable->getID()));
      if ($buildable->getContainerHandle() !== null) {
        $item->addAttribute($buildable->getContainerHandle()->getName());
      }
      if ($buildable->getBuildableHandle() !== null) {
        $item->addAttribute($buildable->getBuildableHandle()->getFullName());
      }

      if ($id) {
        $item->setHref("/B{$id}");
      }

      if ($buildable->getIsManualBuildable()) {
        $item->addIcon('wrench-grey', pht('Manual'));
      }

      $list->addItem($item);



      // TODO: This is proof-of-concept for getting meaningful status
      // information into this list, and should get an improvement pass
      // once we're a little farther along.

      $all_pass = true;
      $any_fail = false;
      foreach ($buildable->getBuilds() as $build) {
        if ($build->getBuildStatus() != HarbormasterBuild::STATUS_PASSED) {
          $all_pass = false;
        }
        if ($build->getBuildStatus() == HarbormasterBuild::STATUS_FAILED ||
            $build->getBuildStatus() == HarbormasterBuild::STATUS_ERROR) {
          $any_fail = true;
        }
      }

      if ($any_fail) {
        $item->setBarColor('red');
      } else if ($all_pass) {
        $item->setBarColor('green');
      }
    }

    return $list;
  }

  public function buildSideNavView($for_app = false) {
    $user = $this->getRequest()->getUser();

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    id(new HarbormasterBuildableSearchEngine())
      ->setViewer($user)
      ->addNavigationItems($nav->getMenu());

    $nav->addLabel(pht('Build Plans'));
    $nav->addFilter('plan/', pht('Manage Build Plans'));

    $nav->selectFilter(null);

    return $nav;
  }

  public function buildApplicationMenu() {
    return $this->buildSideNavView(true)->getMenu();
  }

}
