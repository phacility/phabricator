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

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $pager = new AphrontPagerView();
    $pager->setURI($request->getRequestURI(), 'offset');
    $pager->setOffset($request->getInt('offset'));

    $query = new PhabricatorNotificationQuery();
    $query->setUserPHID($user->getPHID());
    $notifications = $query->executeWithPager($pager);

    if ($notifications) {
      $builder = new PhabricatorNotificationBuilder($notifications);
      $view = $builder->buildView();
    } else {
      $view =
        '<div class="phabricator-notification no-notifications">'.
          'You have no notifications.'.
        '</div>';
    }

    $panel = new AphrontPanelView();
    $panel->setHeader('Notifications');
    $panel->appendChild($view);
    $panel->appendChild($pager);

    return $this->buildStandardPageResponse(
      $panel,
      array(
        'title' => 'Notifications',
      ));
  }

}
