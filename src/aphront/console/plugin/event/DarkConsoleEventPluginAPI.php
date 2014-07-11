<?php

final class DarkConsoleEventPluginAPI extends PhabricatorEventListener {

  private static $events = array();
  private static $discardMode = false;

  public static function enableDiscardMode() {
    self::$discardMode = true;
  }

  public static function getEvents() {
    return self::$events;
  }

  public function register() {
    $this->listen(PhabricatorEventType::TYPE_ALL);
  }

  public function handleEvent(PhutilEvent $event) {
    if (self::$discardMode) {
      return;
    }
    self::$events[] = $event;
  }

}
