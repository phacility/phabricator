<?php

final class PhabricatorMetaMTAWorker
  extends PhabricatorWorker {

  private $message;

  public function getWaitBeforeRetry(PhabricatorWorkerTask $task) {
    $message = $this->loadMessage();
    if (!$message) {
      return null;
    }

    $wait = max($message->getNextRetry() - time(), 0) + 15;
    return $wait;
  }

  public function doWork() {
    $message = $this->loadMessage();
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

  private function loadMessage() {
    if (!$this->message) {
      $message_id = $this->getTaskData();
      $this->message = id(new PhabricatorMetaMTAMail())->load($message_id);
      if (!$this->message) {
        return null;
      }
    }
    return $this->message;
  }

  public function renderForDisplay() {
    return phutil_tag(
      'a',
      array('href' => '/mail/view/'.$this->getTaskData().'/'),
      $this->getTaskData());
  }

}
