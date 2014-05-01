<?php

final class ConduitAPI_maniphest_querystatuses_Method
  extends ConduitAPI_maniphest_Method {

  public function getMethodDescription() {
    return "Retrieve information about possible Maniphest Task status values.";
  }

  public function defineParamTypes() {
    return array();
  }

  public function defineReturnType() {
    return 'nonempty dict<string, wild>';
  }

  public function defineErrorTypes() {
    return array();
  }

  protected function execute(ConduitAPIRequest $request) {
    $results = array(
      'defaultStatus' => ManiphestTaskStatus::getDefaultStatus(),
      'defaultClosedStatus' => ManiphestTaskStatus::getDefaultClosedStatus(),
      'duplicateStatus' => ManiphestTaskStatus::getDuplicateStatus(),
      'openStatuses' => ManiphestTaskStatus::getOpenStatusConstants(),
      'closedStatuses' => ManiphestTaskStatus::getClosedStatusConstants(),
      'allStatuses' => array_keys(ManiphestTaskStatus::getTaskStatusMap()),
      'statusMap' => ManiphestTaskStatus::getTaskStatusMap()
    );
    return $results;
  }

}
