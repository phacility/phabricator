<?php

final class PhabricatorCalendarEventListController
  extends PhabricatorCalendarController {

  private $viewYear;
  private $viewMonth;
  private $viewDay;

  public function shouldAllowPublic() {
    return true;
  }

  public function isGlobalDragAndDropUploadEnabled() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $year = $request->getURIData('year');
    $month = $request->getURIData('month');
    $day = $request->getURIData('day');

    $this->viewYear = $year;
    $this->viewMonth = $month;
    $this->viewDay = $day;

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

    $viewer = $this->getViewer();

    $year = $this->viewYear;
    $month = $this->viewMonth;
    $day = $this->viewDay;

    $parameters = array();

    // If the viewer clicks "Create Event" while on a particular day view,
    // default the times to that day.
    if ($year && $month && $day) {
      $datetimes = PhabricatorCalendarEvent::newDefaultEventDateTimes(
        $viewer,
        PhabricatorTime::getNow());

      foreach ($datetimes as $datetime) {
        $datetime
          ->setYear($year)
          ->setMonth($month)
          ->setDay($day);
      }

      list($start, $end) = $datetimes;
      $parameters['start'] = $start->getEpoch();
      $parameters['end'] = $end->getEpoch();
    }

    id(new PhabricatorCalendarEventEditEngine())
      ->setViewer($this->getViewer())
      ->addActionToCrumbs($crumbs, $parameters);

    return $crumbs;
  }

  protected function buildNavigationItems() {
    $items = array();

    $items[] = id(new PHUIListItemView())
      ->setType(PHUIListItemView::TYPE_LABEL)
      ->setName(pht('Import/Export'));

    $items[] = id(new PHUIListItemView())
      ->setName('Imports')
      ->setHref('/calendar/import/');

    $items[] = id(new PHUIListItemView())
      ->setName('Exports')
      ->setHref('/calendar/export/');

    return $items;
  }

}
