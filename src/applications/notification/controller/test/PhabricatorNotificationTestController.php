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

final class PhabricatorNotificationTestController
  extends PhabricatorNotificationController {

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    $query = new PhabricatorNotificationQuery();
    $query->setUserPHID($user->getPHID());

    $stories = $query->execute();

    $builder = new PhabricatorNotificationBuilder($stories);
    $notifications_view = $builder->buildView();

    $num_unconsumed = 0;

    foreach ($stories as $story) {
      if (!$story->getHasViewed()) {
        $num_unconsumed++;
      }

    }

    $json = array(
      $notifications_view->render()
    );


    return $this->buildStandardPageResponse(
      $json,
      array('title' => 'Notification Test Page'));
  }
}
