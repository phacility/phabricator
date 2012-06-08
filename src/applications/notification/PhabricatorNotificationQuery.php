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

final class PhabricatorNotificationQuery {

  private $limit = 100;
  private $userPHID;


  public function setLimit($limit) {
    $this->limit = $limit;
    return $this;
  }

  public function setUserPHID($user_phid) {
    $this->userPHID = $user_phid;
    return $this;
  }


  public function execute() {
    if (!$this->userPHID) {
      throw new Exception("Call setUser() before executing the query");
    }

    //TODO throw an exception if no user
    $story_table = new PhabricatorFeedStoryData();
    $notification_table = new PhabricatorFeedStoryNotification();

    $conn = $story_table->establishConnection('r');

    $data = queryfx_all(
      $conn,
      "SELECT story.*, notif.hasViewed FROM %T notif
         JOIN %T story ON notif.chronologicalKey = story.chronologicalKey
         WHERE notif.userPHID = %s
         ORDER BY notif.chronologicalKey desc
         LIMIT %d",
      $notification_table->getTableName(),
      $story_table->getTableName(),
      $this->userPHID,
      $this->limit);

    $viewed_map = ipull($data, 'hasViewed', 'chronologicalKey');
    $data = $story_table->loadAllFromArray($data);

    $stories = array();

    foreach ($data as $story_data) {
      $class = $story_data->getStoryType();
      try {
        if (!class_exists($class) ||
          !is_subclass_of($class, 'PhabricatorFeedStory')) {
            $class = 'PhabricatorFeedStoryUnknown';
        }
      } catch (PhutilMissingSymbolException $ex) {
        $class = 'PhabricatorFeedStoryUnknown';
      }
      $story = newv($class, array($story_data));
      $story->setHasViewed($viewed_map[$story->getChronologicalKey()]);
      $stories[] = $story;
    }

    return $stories;
  }
}
