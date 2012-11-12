#!/usr/bin/env php
<?php

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
