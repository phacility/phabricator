<?php

final class PhabricatorCalendarEventListController
  extends PhabricatorCalendarController
  implements PhabricatorApplicationSearchResultsControllerInterface {

  private $queryKey;

  public function willProcessRequest(array $data) {
    $this->queryKey = idx($data, 'queryKey');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $controller = id(new PhabricatorApplicationSearchController($request))
      ->setQueryKey($this->queryKey)
      ->setSearchEngine(new PhabricatorCalendarEventSearchEngine())
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

  public function renderResultsList(
    array $events,
    PhabricatorSavedQuery $query) {
    assert_instances_of($events, 'PhabricatorCalendarEvent');

    $viewer = $this->getRequest()->getUser();

    $list = new PHUIObjectItemListView();
    foreach ($events as $event) {
      if ($event->getUserPHID() == $viewer->getPHID()) {
        $href = $this->getApplicationURI('/event/edit/'.$event->getID().'/');
      } else {
        $from  = $event->getDateFrom();
        $month = phabricator_format_local_time($from, $viewer, 'm');
        $year  = phabricator_format_local_time($from, $viewer, 'Y');
        $uri   = new PhutilURI($this->getApplicationURI());
        $uri->setQueryParams(
          array(
            'month' => $month,
            'year'  => $year,
          ));
        $href = (string) $uri;
      }
      $from = phabricator_datetime($event->getDateFrom(), $viewer);
      $to   = phabricator_datetime($event->getDateTo(), $viewer);

      $color = ($event->getStatus() == PhabricatorCalendarEvent::STATUS_AWAY)
        ? 'red'
        : 'yellow';

      $item = id(new PHUIObjectItemView())
        ->setHeader($event->getTerseSummary($viewer))
        ->setHref($href)
        ->setBarColor($color)
        ->addAttribute(pht('From %s to %s', $from, $to))
        ->addAttribute(
            phutil_utf8_shorten($event->getDescription(), 64));

      $list->addItem($item);
    }

    return $list;
  }

}
