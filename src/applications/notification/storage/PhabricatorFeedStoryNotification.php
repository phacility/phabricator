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

final class PhabricatorFeedStoryNotification extends PhabricatorFeedDAO {

  protected $userPHID;
  protected $primaryObjectPHID;
  protected $chronologicalKey;
  protected $hasViewed;

  public function getConfiguration() {
    return array(
      self::CONFIG_IDS          => self::IDS_MANUAL,
      self::CONFIG_TIMESTAMPS   => false,
    ) + parent::getConfiguration();
  }

  static public function updateObjectNotificationViews(PhabricatorUser $user,
    $object_phid) {

    if (PhabricatorEnv::getEnvConfig('notification.enabled')) {
      $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();

      $notification_table = new PhabricatorFeedStoryNotification();
      $conn = $notification_table->establishConnection('w');

      queryfx(
        $conn,
        "UPDATE %T
         SET hasViewed = 1
         WHERE userPHID = %s
           AND primaryObjectPHID = %s
           AND hasViewed = 0",
        $notification_table->getTableName(),
        $user->getPHID(),
        $object_phid);

      unset($unguarded);
    }
  }

  /* should only be called when notifications are enabled */
  public function countUnread(
    PhabricatorUser $user) {

      $conn = $this->establishConnection('r');

      $data = queryfx_one(
        $conn,
        "SELECT COUNT(*) as count
         FROM %T
         WHERE userPHID = %s
           AND hasViewed=0",
        $this->getTableName(),
        $user->getPHID());

      return $data['count'];
  }

}
