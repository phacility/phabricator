<?php

final class ManiphestQueryStatusesConduitAPIMethod
  extends ManiphestConduitAPIMethod {

  public function getAPIMethodName() {
    return 'maniphest.querystatuses';
  }

  public function getMethodDescription() {
    return pht(
      'Retrieve information about possible Maniphest task status values.');
  }

  protected function defineParamTypes() {
    return array();
  }

  protected function defineReturnType() {
    return 'nonempty dict<string, wild>';
  }

  protected function execute(ConduitAPIRequest $request) {
    $results = array(
      'defaultStatus' => ManiphestTaskStatus::getDefaultStatus(),
      'defaultClosedStatus' => ManiphestTaskStatus::getDefaultClosedStatus(),
      'duplicateStatus' => ManiphestTaskStatus::getDuplicateStatus(),
      'openStatuses' => ManiphestTaskStatus::getOpenStatusConstants(),
      'closedStatuses' => ManiphestTaskStatus::getClosedStatusConstants(),
      'allStatuses' => array_keys(ManiphestTaskStatus::getTaskStatusMap()),
      'statusMap' => ManiphestTaskStatus::getTaskStatusMap(),
    );
    return $results;
  }

}
