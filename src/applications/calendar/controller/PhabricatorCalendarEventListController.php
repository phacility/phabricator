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

    $nav_items = $this->buildNavigationItems();

    return $engine
      ->setNavigationItems($nav_items)
      ->setController($this)
      ->buildResponse();
  }

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    id(new PhabricatorCalendarEventEditEngine())
      ->setViewer($this->getViewer())
      ->addActionToCrumbs($crumbs);

    return $crumbs;
  }

  protected function buildNavigationItems() {
    $items = array();

    $items[] = id(new PHUIListItemView())
      ->setType(PHUIListItemView::TYPE_LABEL)
      ->setName(pht('Import/Export'));

    $items[] = id(new PHUIListItemView())
      ->setName('Exports')
      ->setHref('/calendar/export/');

    return $items;
  }

}
