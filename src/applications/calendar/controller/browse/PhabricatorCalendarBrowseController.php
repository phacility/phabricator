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
    $request = $this->getRequest();
    $user = $request->getUser();

    // TODO: These should be user-based and navigable in the interface.
    $year = idate('Y');
    $month = idate('m');

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
    $month_view->setUser($user);
    $month_view->setHolidays($holidays);

    $phids = mpull($statuses, 'getUserPHID');
    $handles = id(new PhabricatorObjectHandleData($phids))->loadHandles();

    foreach ($statuses as $status) {
      $event = new AphrontCalendarEventView();
      $event->setEpochRange($status->getDateFrom(), $status->getDateTo());

      $name_text = $handles[$status->getUserPHID()]->getName();
      $status_text = $status->getTextStatus();
      $event->setUserPHID($status->getUserPHID());
      $event->setName("{$name_text} ({$status_text})");
      $event->setDescription($status->getStatusDescription($user));
      $month_view->addEvent($event);
    }

    return $this->buildStandardPageResponse(
      array(
        '<div style="padding: 2em;">',
          $month_view,
        '</div>',
      ),
      array(
        'title' => 'Calendar',
      ));
  }
}
