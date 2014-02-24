<?php

final class PhabricatorPeopleProfileController
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

    require_celerity_resource('phabricator-profile-css');

    $profile = $user->loadUserProfile();
    $username = phutil_escape_uri($user->getUserName());

    $picture = $user->loadProfileImageURI();

    $header = id(new PHUIHeaderView())
      ->setHeader($user->getUserName().' ('.$user->getRealName().')')
      ->setSubheader($profile->getTitle())
      ->setImage($picture);

    $actions = id(new PhabricatorActionListView())
      ->setObject($user)
      ->setObjectURI($this->getRequest()->getRequestURI())
      ->setUser($viewer);

    $can_edit = ($user->getPHID() == $viewer->getPHID());

    $actions->addAction(
      id(new PhabricatorActionView())
        ->setIcon('edit')
        ->setName(pht('Edit Profile'))
        ->setHref($this->getApplicationURI('editprofile/'.$user->getID().'/'))
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    $actions->addAction(
      id(new PhabricatorActionView())
        ->setIcon('image')
        ->setName(pht('Edit Profile Picture'))
        ->setHref($this->getApplicationURI('picture/'.$user->getID().'/'))
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    if ($viewer->getIsAdmin()) {
      $actions->addAction(
        id(new PhabricatorActionView())
          ->setIcon('blame')
          ->setName(pht('Administrate User'))
          ->setHref($this->getApplicationURI('edit/'.$user->getID().'/')));
    }

    $properties = $this->buildPropertyView($user, $actions);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb($user->getUsername());
    $feed = $this->renderUserFeed($user);
    $calendar = $this->renderUserCalendar($user);
    $activity = phutil_tag(
      'div',
      array(
        'class' => 'profile-activity-view grouped'
      ),
      array(
        $calendar,
        $feed
      ));

    $object_box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($properties);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $object_box,
        $activity,
      ),
      array(
        'title' => $user->getUsername(),
        'device' => true,
      ));
  }

  private function buildPropertyView(
    PhabricatorUser $user,
    PhabricatorActionListView $actions) {

    $viewer = $this->getRequest()->getUser();
    $view = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setObject($user)
      ->setActionList($actions);

    $field_list = PhabricatorCustomField::getObjectFields(
      $user,
      PhabricatorCustomField::ROLE_VIEW);
    $field_list->appendFieldsToPropertyList($user, $viewer, $view);

    return $view;
  }

  private function renderUserFeed(PhabricatorUser $user) {
    $viewer = $this->getRequest()->getUser();

    $query = new PhabricatorFeedQuery();
    $query->setFilterPHIDs(
      array(
        $user->getPHID(),
      ));
    $query->setLimit(100);
    $query->setViewer($viewer);
    $stories = $query->execute();

    $builder = new PhabricatorFeedBuilder($stories);
    $builder->setUser($viewer);
    $builder->setShowHovercards(true);
    $view = $builder->buildView();

    return phutil_tag_div(
      'profile-feed',
      $view->render());
  }

  private function renderUserCalendar(PhabricatorUser $user) {
    $now = time();
    $year = phabricator_format_local_time($now, $user, 'Y');
    $month = phabricator_format_local_time($now, $user, 'm');
    $day = phabricator_format_local_time($now, $user, 'j');
    $statuses = id(new PhabricatorCalendarEventQuery())
      ->setViewer($user)
      ->withInvitedPHIDs(array($user->getPHID()))
      ->withDateRange(
        strtotime("{$year}-{$month}-{$day}"),
        strtotime("{$year}-{$month}-{$day} +7 days"))
      ->execute();

    $events = array();
    foreach ($statuses as $status) {
      $event = new AphrontCalendarEventView();
      $event->setEpochRange($status->getDateFrom(), $status->getDateTo());

      $status_text = $status->getHumanStatus();
      $event->setUserPHID($status->getUserPHID());
      $event->setName($status_text);
      $event->setDescription($status->getDescription());
      $event->setEventID($status->getID());
      $key = date('Y-m-d', $event->getEpochStart());
      $events[$key][] = $event;
      // Populate multiday events
      // Better means?
      $next_day = strtotime("{$key} +1 day");
      if ($event->getEpochEnd() >= $next_day) {
        $nextkey = date('Y-m-d', $next_day);
        $events[$nextkey][] = $event;
      }
    }

    $i = 0;
    $week = array();
    for ($i = 0;$i <= 6;$i++) {
      $datetime = strtotime("{$year}-{$month}-{$day} +{$i} days");
      $headertext = phabricator_format_local_time($datetime, $user, 'l, M d');
      $this_day = date('Y-m-d', $datetime);

      $list = new PHUICalendarListView();
      $list->setUser($user);
      $list->showBlankState(true);
      if (isset($events[$this_day])) {
        foreach ($events[$this_day] as $event) {
          $list->addEvent($event);
        }
      }

      $header = phutil_tag(
        'a',
        array(
          'href' => $this->getRequest()->getRequestURI().'calendar/'
        ),
        $headertext);

      $calendar = new PHUICalendarWidgetView();
      $calendar->setHeader($header);
      $calendar->setCalendarList($list);
      $week[] = $calendar;
    }

    return phutil_tag_div(
      'profile-calendar',
      $week);
  }
}
