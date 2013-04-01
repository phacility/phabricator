<?php

final class PhrequentListController extends PhrequentController {

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $nav = $this->buildNav('usertime');

    $query = new PhrequentUserTimeQuery();
    $query->setOrder(PhrequentUserTimeQuery::ORDER_ENDED);

    $pager = new AphrontPagerView();
    $pager->setPageSize(500);
    $pager->setOffset($request->getInt('offset'));
    $pager->setURI($request->getRequestURI(), 'offset');

    $logs = $query->executeWithOffsetPager($pager);

    $title = pht('Time Tracked');

    $header = id(new PhabricatorHeaderView())
      ->setHeader($title);

    $table = $this->buildTableView($logs);
    $table->appendChild($pager);

    $nav->appendChild(
      array(
        $header,
        $table,
        $pager,
      ));

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName($title)
        ->setHref($this->getApplicationURI('/')));
    $nav->setCrumbs($crumbs);

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => $title,
        'device' => true,
      ));

  }

  protected function buildTableView(array $usertimes) {
    assert_instances_of($usertimes, 'PhrequentUserTime');

    $user = $this->getRequest()->getUser();

    $phids = array();
    foreach ($usertimes as $usertime) {
      $phids[] = $usertime->getUserPHID();
      $phids[] = $usertime->getObjectPHID();
    }
    $handles = $this->loadViewerHandles($phids);

    $rows = array();
    foreach ($usertimes as $usertime) {

      if ($usertime->getDateEnded() !== null) {
        $time_spent = $usertime->getDateEnded() - $usertime->getDateStarted();
        $time_ended = phabricator_datetime($usertime->getDateEnded(), $user);
      } else {
        $time_spent = time() - $usertime->getDateStarted();
        $time_ended = phutil_tag(
          'em',
          array(),
          pht('Ongoing'));
      }

      $usertime_user = $handles[$usertime->getUserPHID()];
      $usertime_object = null;
      $object = null;
      if ($usertime->getObjectPHID() !== null) {
        $usertime_object = $handles[$usertime->getObjectPHID()];
        $object = phutil_tag(
          'a',
          array(
            'href' => $usertime_object->getURI()
          ),
          $usertime_object->getFullName());
      } else {
        $object = phutil_tag(
          'em',
          array(),
          pht('None'));
      }

      $rows[] = array(
        $object,
        phutil_tag(
          'a',
          array(
            'href' => $usertime_user->getURI()
          ),
          $usertime_user->getFullName()),
        phabricator_datetime($usertime->getDateStarted(), $user),
        $time_ended,
        $time_spent == 0 ? 'none' :
          phabricator_format_relative_time_detailed($time_spent),
        $usertime->getNote()
      );
    }

    $table = new AphrontTableView($rows);
    $table->setDeviceReadyTable(true);
    $table->setHeaders(
      array(
        'Object',
        'User',
        'Started',
        'Ended',
        'Duration',
        'Note'
      ));
    $table->setShortHeaders(
      array(
        'O',
        'U',
        'S',
        'E',
        'D',
        'Note',
        '',
      ));
    $table->setColumnClasses(
      array(
        '',
        '',
        '',
        '',
        '',
        'wide'
      ));

    return $table;
  }

}
