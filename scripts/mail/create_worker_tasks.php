#!/usr/bin/env php
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

/*
 * After upgrading to/past D1723, the handling of messages queued for delivery
 * is a bit different. Running this script will take any messages that are
 * queued for delivery, but don't have a worker task created, and create that
 * worker task. Without the worker task, the message will just sit at "queued
 * for delivery" and nothing will happen to it.
 */

$root = dirname(dirname(dirname(__FILE__)));
require_once $root.'/scripts/__init_script__.php';

$messages = id(new PhabricatorMetaMTAMail())->loadAllWhere(
  'status = %s', PhabricatorMetaMTAMail::STATUS_QUEUE);

foreach ($messages as $message) {
  if (!$message->getWorkerTaskID()) {
    $mailer_task = PhabricatorWorker::scheduleTask(
      'PhabricatorMetaMTAWorker',
      $message->getID());

    $message->setWorkerTaskID($mailer_task->getID());
    $message->save();
    $id = $message->getID();
    echo "#$id\n";
  }
}
