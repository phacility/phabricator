<?php

final class PhabricatorCalendarEventListController
  extends PhabricatorCalendarController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $year = $request->getURIData('year');
    $month = $request->getURIData('month');
    $day = $request->getURIData('day');
    $engine = new PhabricatorCalendarEventSearchEngine();

    if ($month && $year) {
      $engine->setCalendarYearAndMonthAndDay($year, $month, $day);
    }

    $controller = id(new PhabricatorApplicationSearchController())
      ->setQueryKey($request->getURIData('queryKey'))
      ->setSearchEngine($engine);

    return $this->delegateToController($controller);
  }

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    id(new PhabricatorCalendarEventEditEngine())
      ->setViewer($this->getViewer())
      ->addActionToCrumbs($crumbs);

    return $crumbs;
  }

}
