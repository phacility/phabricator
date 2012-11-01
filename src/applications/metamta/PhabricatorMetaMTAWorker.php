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

final class PhabricatorMetaMTAWorker
  extends PhabricatorWorker {

  private $message;

  public function getWaitBeforeRetry(PhabricatorWorkerTask $task) {
    $message_id = $this->getTaskData();

    $this->message = id(new PhabricatorMetaMTAMail())->loadOneWhere(
      'id = %d', $this->getTaskData());
    if (!$this->message) {
      return null;
    }

    $wait = max($this->message->getNextRetry() - time(), 0) + 15;
    return $wait;
  }

  public function doWork() {
    $message = $this->message;
    if (!$message
        || $message->getStatus() != PhabricatorMetaMTAMail::STATUS_QUEUE) {
      return;
    }
    $id = $message->getID();
    $message->sendNow();
    // task failed if the message is still queued
    // (instead of sent, void, or failed)
    if ($message->getStatus() == PhabricatorMetaMTAMail::STATUS_QUEUE) {
      throw new Exception('Failed to send message');
    }
  }
}
