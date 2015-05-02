<?php

final class PhabricatorCalendarEventListController
  extends PhabricatorCalendarController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $year = $request->getURIData('year');
    $month = $request->getURIData('month');
    $engine = new PhabricatorCalendarEventSearchEngine();

    if ($month && $year) {
      $engine->setCalendarYearAndMonth($year, $month);
    }

    $controller = id(new PhabricatorApplicationSearchController())
      ->setQueryKey($request->getURIData('queryKey'))
      ->setSearchEngine($engine)
      ->setNavigation($this->buildSideNav());
    return $this->delegateToController($controller);
  }

  public function buildSideNav() {
    $user = $this->getRequest()->getUser();

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    id(new PhabricatorCalendarEventSearchEngine())
      ->setViewer($user)
      ->addNavigationItems($nav->getMenu());

    $nav->selectFilter(null);

    return $nav;
  }

}
