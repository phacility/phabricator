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

/**
 * @group irc
 */
final class PhabricatorIRCDifferentialNotificationHandler
  extends PhabricatorIRCHandler {

  private $skippedOldEvents;

  public function receiveMessage(PhabricatorIRCMessage $message) {
    return;
  }

  public function runBackgroundTasks() {
    $iterator = new PhabricatorTimelineIterator('ircdiffx', array('difx'));
    $show = $this->getConfig('notification.actions');

    if (!$this->skippedOldEvents) {
      // Since we only want to post notifications about new events, skip
      // everything that's happened in the past when we start up so we'll
      // only process real-time events.
      foreach ($iterator as $event) {
        // Ignore all old events.
      }
      $this->skippedOldEvents = true;
      return;
    }

    foreach ($iterator as $event) {
      $data = $event->getData();
      if (!$data || ($show !== null && !in_array($data['action'], $show))) {
        continue;
      }

      $actor_phid = $data['actor_phid'];
      $phids = array($actor_phid);
      $handles = id(new PhabricatorObjectHandleData($phids))->loadHandles();
      $verb = DifferentialAction::getActionPastTenseVerb($data['action']);

      $actor_name = $handles[$actor_phid]->getName();
      $message = "{$actor_name} {$verb} revision D".$data['revision_id'].".";

      $channels = $this->getConfig('notification.channels', array());
      foreach ($channels as $channel) {
        $this->write('PRIVMSG', "{$channel} :{$message}");
      }
    }
  }

}
