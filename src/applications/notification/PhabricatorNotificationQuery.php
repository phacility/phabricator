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

final class PhabricatorNotificationQuery extends PhabricatorOffsetPagedQuery {

  private $userPHID;
  private $keys;

  public function setUserPHID($user_phid) {
    $this->userPHID = $user_phid;
    return $this;
  }

  public function withKeys(array $keys) {
    $this->keys = $keys;
    return $this;
  }

  public function execute() {
    if (!$this->userPHID) {
      throw new Exception("Call setUser() before executing the query");
    }

    $story_table = new PhabricatorFeedStoryData();
    $notification_table = new PhabricatorFeedStoryNotification();

    $conn = $story_table->establishConnection('r');

    $data = queryfx_all(
      $conn,
      "SELECT story.*, notif.primaryObjectPHID, notif.hasViewed FROM %T notif
         JOIN %T story ON notif.chronologicalKey = story.chronologicalKey
         %Q
         ORDER BY notif.chronologicalKey DESC
         %Q",
      $notification_table->getTableName(),
      $story_table->getTableName(),
      $this->buildWhereClause($conn),
      $this->buildLimitClause($conn));

    $viewed_map = ipull($data, 'hasViewed', 'chronologicalKey');
    $primary_map = ipull($data, 'primaryObjectPHID', 'chronologicalKey');

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
      $story->setPrimaryObjectPHID($primary_map[$story->getChronologicalKey()]);
      $stories[] = $story;
    }

    return $stories;
  }

  private function buildWhereClause(AphrontDatabaseConnection $conn_r) {
    $where = array();

    if ($this->userPHID) {
      $where[] = qsprintf(
        $conn_r,
        'notif.userPHID = %s',
        $this->userPHID);
    }

    if ($this->keys) {
      $where[] = qsprintf(
        $conn_r,
        'notif.chronologicalKey IN (%Ls)',
        $this->keys);
    }

    return $this->formatWhereClause($where);
  }

}
