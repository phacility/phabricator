<?php

final class PhabricatorCalendarEventSearchConduitAPIMethod
  extends PhabricatorSearchEngineAPIMethod {

  public function getAPIMethodName() {
    return 'calendar.event.search';
  }

  public function newSearchEngine() {
    return new PhabricatorCalendarEventSearchEngine();
  }

  public function getMethodSummary() {
    return pht('Read information about events.');
  }

}
