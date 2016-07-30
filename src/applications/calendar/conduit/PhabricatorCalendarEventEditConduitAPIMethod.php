<?php

final class PhabricatorCalendarEventEditConduitAPIMethod
  extends PhabricatorEditEngineAPIMethod {

  public function getAPIMethodName() {
    return 'calendar.event.edit';
  }

  public function newEditEngine() {
    return new PhabricatorCalendarEventEditEngine();
  }

  public function getMethodSummary() {
    return pht(
      'Apply transactions to create a new event or edit an existing one.');
  }

}
