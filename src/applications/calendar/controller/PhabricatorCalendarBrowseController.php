<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

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

    $holidays = id(new PhabricatorCalendarHoliday())->loadAllWhere(
      'day BETWEEN %s AND %s',
      "{$year}-{$month}-01",
      "{$year}-{$month}-31");

    $statuses = id(new PhabricatorUserStatus())
      ->loadAllWhere(
        'dateTo >= %d AND dateFrom <= %d',
        strtotime("{$year}-{$month}-01"),
        strtotime("{$year}-{$month}-01 next month"));

    $month_view = new AphrontCalendarMonthView($month, $year);
    $month_view->setBrowseURI($request->getRequestURI());
    $month_view->setUser($user);
    $month_view->setHolidays($holidays);

    $phids = mpull($statuses, 'getUserPHID');
    $handles = $this->loadViewerHandles($phids);

    foreach ($statuses as $status) {
      $event = new AphrontCalendarEventView();
      $event->setEpochRange($status->getDateFrom(), $status->getDateTo());

      $name_text = $handles[$status->getUserPHID()]->getName();
      $status_text = $status->getTextStatus();
      $event->setUserPHID($status->getUserPHID());
      $event->setName("{$name_text} ({$status_text})");
      $details = '';
      if ($status->getDescription()) {
        $details = "\n\n".rtrim(phutil_escape_html($status->getDescription()));
      }
      $event->setDescription(
        $status->getTerseSummary($user).$details
      );
      $month_view->addEvent($event);
    }

    $nav = $this->buildSideNavView();
    $nav->selectFilter('edit');
    $nav->appendChild(
      array(
        $this->getNoticeView(),
        '<div style="padding: 2em;">',
          $month_view,
        '</div>',
      ));

    return $this->buildApplicationPage(
     $nav,
     array(
        'title' => 'Calendar',
        'device' => true,
      ));
  }

  private function getNoticeView() {
    $request = $this->getRequest();
    $view    = null;

    if ($request->getExists('created')) {
      $view = id(new AphrontErrorView())
        ->setSeverity(AphrontErrorView::SEVERITY_NOTICE)
        ->setTitle(pht('Successfully created your status.'));
    } else if ($request->getExists('updated')) {
      $view = id(new AphrontErrorView())
        ->setSeverity(AphrontErrorView::SEVERITY_NOTICE)
        ->setTitle(pht('Successfully updated your status.'));
    } else if ($request->getExists('deleted')) {
      $view = id(new AphrontErrorView())
        ->setSeverity(AphrontErrorView::SEVERITY_NOTICE)
        ->setTitle(pht('Successfully deleted your status.'));
    }

    return $view;
  }

}
