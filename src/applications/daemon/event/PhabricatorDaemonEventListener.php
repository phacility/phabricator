<?php

final class PhabricatorDaemonEventListener extends PhabricatorEventListener {

  private $daemons = array();

  public function register() {
    $this->listen(PhutilDaemonOverseer::EVENT_DID_LAUNCH);
    $this->listen(PhutilDaemonOverseer::EVENT_DID_LOG);
    $this->listen(PhutilDaemonOverseer::EVENT_DID_HEARTBEAT);
    $this->listen(PhutilDaemonOverseer::EVENT_WILL_EXIT);
  }

  public function handleEvent(PhutilEvent $event) {
    switch ($event->getType()) {
      case PhutilDaemonOverseer::EVENT_DID_LAUNCH:
        $this->handleLaunchEvent($event);
        break;
      case PhutilDaemonOverseer::EVENT_DID_HEARTBEAT:
        $this->handleHeartbeatEvent($event);
        break;
      case PhutilDaemonOverseer::EVENT_DID_LOG:
        $this->handleLogEvent($event);
        break;
      case PhutilDaemonOverseer::EVENT_WILL_EXIT:
        $this->handleExitEvent($event);
        break;
    }
  }

  private function handleLaunchEvent(PhutilEvent $event) {
    $id = $event->getValue('id');

    $daemon = id(new PhabricatorDaemonLog())
      ->setDaemon($event->getValue('daemonClass'))
      ->setHost(php_uname('n'))
      ->setPID(getmypid())
      ->setStatus(PhabricatorDaemonLog::STATUS_RUNNING)
      ->setArgv($event->getValue('argv'))
      ->setExplicitArgv($event->getValue('explicitArgv'))
      ->save();

    $this->daemons[$id] = $daemon;
  }

  private function handleHeartbeatEvent(PhutilEvent $event) {
    $daemon = $this->getDaemon($event->getValue('id'));

    // Just update the timestamp.
    $daemon->save();
  }

  private function handleLogEvent(PhutilEvent $event) {
    $daemon = $this->getDaemon($event->getValue('id'));

    // TODO: This is a bit awkward for historical reasons, clean it up after
    // removing Conduit.
    $message = $event->getValue('message');
    $context = $event->getValue('context');
    if (strlen($context) && $context !== $message) {
      $message = "({$context}) {$message}";
    }

    $type = $event->getValue('type');

    $message = phutil_utf8ize($message);

    id(new PhabricatorDaemonLogEvent())
      ->setLogID($daemon->getID())
      ->setLogType($type)
      ->setMessage((string)$message)
      ->setEpoch(time())
      ->save();

    switch ($type) {
      case 'WAIT':
        $current_status = PhabricatorDaemonLog::STATUS_WAIT;
        break;
      default:
        $current_status = PhabricatorDaemonLog::STATUS_RUNNING;
        break;
    }

    if ($current_status !== $daemon->getStatus()) {
      $daemon->setStatus($current_status)->save();
    }
  }

  private function handleExitEvent(PhutilEvent $event) {
    $id = $event->getValue('id');

    $daemon = $this->getDaemon($id);
    $daemon->setStatus(PhabricatorDaemonLog::STATUS_EXITED)->save();

    unset($this->daemons[$id]);
  }

  private function getDaemon($id) {
    if (isset($this->daemons[$id])) {
      return $this->daemons[$id];
    }
    throw new Exception("No such daemon '{$id}'!");
  }

}
