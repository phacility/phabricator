<?php

final class PhabricatorCalendarEventFerretEngine
  extends PhabricatorFerretEngine {

  public function getApplicationName() {
    return 'calendar';
  }

  public function getScopeName() {
    return 'event';
  }

  public function newSearchEngine() {
    return new PhabricatorCalendarEventSearchEngine();
  }

}
