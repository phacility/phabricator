<?php

final class ConpherenceRoomListController extends ConpherenceController {

  public function handleRequest(AphrontRequest $request) {
    $user = $request->getUser();

    $controller = id(new PhabricatorApplicationSearchController())
      ->setQueryKey($request->getURIData('queryKey'))
      ->setSearchEngine(
        id(new ConpherenceThreadSearchEngine())
        ->setIsRooms(true))
      ->setNavigation($this->buildRoomsSideNavView());

    return $this->delegateToController($controller);
  }

  protected function buildApplicationCrumbs() {
    return $this->buildConpherenceApplicationCrumbs($is_rooms = true);
  }

  public function buildApplicationMenu() {
    return $this->buildRoomsSideNavView(true)->getMenu();
  }

  private function buildRoomsSideNavView($for_app = false) {
    $user = $this->getRequest()->getUser();

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    if ($for_app) {
      $nav->addFilter('room/new/', pht('Create Room'));
    }

    id(new ConpherenceThreadSearchEngine())
      ->setViewer($user)
      ->addNavigationItems($nav->getMenu());

    $nav->selectFilter(null);

    return $nav;
  }


}
