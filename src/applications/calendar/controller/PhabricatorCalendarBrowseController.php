<?php

final class PhabricatorCalendarBrowseController
  extends PhabricatorCalendarController {

  public function processRequest() {
    $now     = time();
    $request = $this->getRequest();
    $user    = $request->getUser();
    $year_d  = phabricator_format_local_time($now, $user, 'Y');
    $year    = $request->getInt('year', $year_d);
    $month_d = phabricator_format_local_time($now, $user, 'm');
    $month   = $request->getInt('month', $month_d);
    $day   = phabricator_format_local_time($now, $user, 'j');


    $holidays = id(new PhabricatorCalendarHoliday())->loadAllWhere(
      'day BETWEEN %s AND %s',
      "{$year}-{$month}-01",
      "{$year}-{$month}-31");

    $statuses = id(new PhabricatorCalendarEventQuery())
      ->setViewer($user)
      ->withDateRange(
        strtotime("{$year}-{$month}-01"),
        strtotime("{$year}-{$month}-01 next month"))
      ->execute();

    if ($month == $month_d && $year == $year_d) {
      $month_view = new PHUICalendarMonthView($month, $year, $day);
    } else {
      $month_view = new PHUICalendarMonthView($month, $year);
    }

    $month_view->setBrowseURI($request->getRequestURI());
    $month_view->setUser($user);
    $month_view->setHolidays($holidays);

    $phids = mpull($statuses, 'getUserPHID');
    $handles = $this->loadViewerHandles($phids);

    /* Assign Colors */
    $unique = array_unique($phids);
    $allblue = false;
    $calcolors = CalendarColors::getColors();
    if (count($unique) > count($calcolors)) {
      $allblue = true;
    }
    $i = 0;
    $eventcolor = array();
    foreach ($unique as $phid) {
      if ($allblue) {
        $eventcolor[$phid] = CalendarColors::COLOR_SKY;
      } else {
        $eventcolor[$phid] = $calcolors[$i];
      }
      $i++;
    }

    foreach ($statuses as $status) {
      $event = new AphrontCalendarEventView();
      $event->setEpochRange($status->getDateFrom(), $status->getDateTo());

      $name_text = $handles[$status->getUserPHID()]->getName();
      $status_text = $status->getHumanStatus();
      $event->setUserPHID($status->getUserPHID());
      $event->setDescription(pht('%s (%s)', $name_text, $status_text));
      $event->setName($status_text);
      $event->setEventID($status->getID());
      $event->setColor($eventcolor[$status->getUserPHID()]);
      $month_view->addEvent($event);
    }

    $date = new DateTime("{$year}-{$month}-01");
    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('All Events'));
    $crumbs->addTextCrumb($date->format('F Y'));

    $nav = $this->buildSideNavView();
    $nav->selectFilter('all/');
    $nav->appendChild(
      array(
        $crumbs,
        $month_view,
      ));

    return $this->buildApplicationPage(
     $nav,
     array(
        'title' => pht('Calendar'),
        'device' => true,
      ));
  }

}
