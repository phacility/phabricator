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

final class PhabricatorNotificationListController
  extends PhabricatorNotificationController {

  private $filter;

  public function willProcessRequest(array $data) {
    $this->filter = idx($data, 'filter');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI('/notification/'));
    $nav->addFilter('all', 'All Notifications');
    $nav->addFilter('unread', 'Unread Notifications');
    $filter = $nav->selectFilter($this->filter, 'all');

    $pager = new AphrontPagerView();
    $pager->setURI($request->getRequestURI(), 'offset');
    $pager->setOffset($request->getInt('offset'));

    $query = new PhabricatorNotificationQuery();
    $query->setViewer($user);
    $query->setUserPHID($user->getPHID());

    switch ($filter) {
      case 'unread':
        $query->withUnread(true);
        $header = pht('Unread Notifications');
        $no_data = pht('You have no unread notifications.');
        break;
      default:
        $header = pht('Notifications');
        $no_data = pht('You have no notifications.');
        break;
    }

    $notifications = $query->executeWithOffsetPager($pager);

    if ($notifications) {
      $builder = new PhabricatorNotificationBuilder($notifications);
      $view = $builder->buildView();
    } else {
      $view =
        '<div class="phabricator-notification no-notifications">'.
          $no_data.
        '</div>';
    }

    $view = array(
      '<div class="phabricator-notification-list">',
      $view,
      '</div>',
    );

    $panel = new AphrontPanelView();
    $panel->setHeader($header);
    $panel->setWidth(AphrontPanelView::WIDTH_FORM);
    $panel->addButton(
      javelin_render_tag(
        'a',
        array(
          'href'  => '/notification/clear/',
          'class' => 'button',
          'sigil' => 'workflow',
        ),
        'Mark All Read'));
    $panel->appendChild($view);
    $panel->appendChild($pager);

    $nav->appendChild($panel);

    return $this->buildStandardPageResponse(
      $nav,
      array(
        'title' => 'Notifications',
      ));
  }

}
