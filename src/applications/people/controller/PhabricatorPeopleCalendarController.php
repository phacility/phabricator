<?php

final class PhabricatorPeopleCalendarController
  extends PhabricatorPeopleController {

  private $username;

  public function shouldRequireAdmin() {
    return false;
  }

  public function willProcessRequest(array $data) {
    $this->username = idx($data, 'username');
  }

  public function processRequest() {
    $viewer = $this->getRequest()->getUser();
    $user = id(new PhabricatorPeopleQuery())
      ->setViewer($viewer)
      ->withUsernames(array($this->username))
      ->needProfileImage(true)
      ->executeOne();

    if (!$user) {
      return new Aphront404Response();
    }

    $picture = $user->getProfileImageURI();

    $now     = time();
    $request = $this->getRequest();
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
      ->withInvitedPHIDs(array($user->getPHID()))
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
    $month_view->setImage($picture);

    $phids = mpull($statuses, 'getUserPHID');
    $handles = $this->loadViewerHandles($phids);

    foreach ($statuses as $status) {
      $event = new AphrontCalendarEventView();
      $event->setEpochRange($status->getDateFrom(), $status->getDateTo());
      $event->setUserPHID($status->getUserPHID());
      $event->setName($status->getHumanStatus());
      $event->setDescription($status->getDescription());
      $event->setEventID($status->getID());
      $month_view->addEvent($event);
    }

    $name = $user->getUsername();

    $nav = $this->buildIconNavView($user);
    $nav->selectFilter("{$name}/calendar/");
    $nav->appendChild($month_view);

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => pht('Calendar'),
      ));
  }
}
