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

final class PhabricatorCalendarViewStatusController
  extends PhabricatorCalendarController {

  private $phid;

  public function willProcessRequest(array $data) {
    $user = $this->getRequest()->getUser();
    $this->phid = idx($data, 'phid', $user->getPHID());
    $this->loadHandles(array($this->phid));
  }

  public function processRequest() {

    $request  = $this->getRequest();
    $user     = $request->getUser();
    $handle   = $this->getHandle($this->phid);
    $statuses = id(new PhabricatorUserStatus())
      ->loadAllWhere('userPHID = %s AND dateTo > UNIX_TIMESTAMP()',
                     $this->phid);

    $nav = $this->buildSideNavView();
    $nav->selectFilter($this->getFilter());

    $page_title = $this->getPageTitle();

    $status_list = $this->buildStatusList($statuses);
    $status_list->setNoDataString($this->getNoDataString());

    $nav->appendChild(
      array(
        id(new PhabricatorHeaderView())->setHeader($page_title),
        $status_list,
      )
    );

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => $page_title,
        'device' => true
      )
    );
  }

  private function buildStatusList(array $statuses) {
    assert_instances_of($statuses, 'PhabricatorUserStatus');

    $user = $this->getRequest()->getUser();

    $list = new PhabricatorObjectItemListView();
    foreach ($statuses as $status) {
      if ($status->getUserPHID() == $user->getPHID()) {
        $href = $this->getApplicationURI('/status/edit/'.$status->getID().'/');
      } else {
        $from  = $status->getDateFrom();
        $month = phabricator_format_local_time($from, $user, 'm');
        $year  = phabricator_format_local_time($from, $user, 'Y');
        $uri   = new PhutilURI($this->getApplicationURI());
        $uri->setQueryParams(
          array(
            'month' => $month,
            'year'  => $year,
          )
        );
        $href = (string) $uri;
      }
      $from = phabricator_datetime($status->getDateFrom(), $user);
      $to   = phabricator_datetime($status->getDateTo(), $user);
      $item = id(new PhabricatorObjectItemView())
        ->setHeader($status->getTerseSummary($user))
        ->setHref($href)
        ->addDetail(
          pht('Description'),
          $status->getDescription())
        ->addAttribute(pht('From %s', $from))
        ->addAttribute(pht('To %s', $to));

      $list->addItem($item);
    }

    return $list;
  }

  private function getNoDataString() {
    if ($this->isUserRequest()) {
      $no_data =
        pht('You do not have any upcoming status events.');
    } else {
      $no_data =
        pht('%s does not have any upcoming status events.',
            phutil_escape_html($this->getHandle($this->phid)->getName()));
    }
    return $no_data;
  }

  private function getFilter() {
    if ($this->isUserRequest()) {
      $filter = 'status/';
    } else {
      $filter = 'status/view/'.$this->phid.'/';
    }

    return $filter;
  }

  private function getPageTitle() {
    if ($this->isUserRequest()) {
      $page_title = pht('Upcoming Statuses');
    } else {
      $page_title = pht(
        'Upcoming Statuses for %s',
        phutil_escape_html($this->getHandle($this->phid)->getName())
      );
    }
    return $page_title;
  }

  private function isUserRequest() {
    $user = $this->getRequest()->getUser();
    return $this->phid == $user->getPHID();
  }

}
