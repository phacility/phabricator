<?php

final class ManiphestPrioritySearchConduitAPIMethod
  extends ManiphestConduitAPIMethod {

  public function getAPIMethodName() {
    return 'maniphest.priority.search';
  }

  public function getMethodSummary() {
    return pht('Read information about task priorities.');
  }

  public function getMethodDescription() {
    return pht(
      'Returns information about the possible priorities for Maniphest '.
      'tasks.');
  }

  protected function defineParamTypes() {
    return array();
  }

  protected function defineReturnType() {
    return 'map<string, wild>';
  }

  public function getRequiredScope() {
    return self::SCOPE_ALWAYS;
  }

  protected function execute(ConduitAPIRequest $request) {
    $config = ManiphestTaskPriority::getConfig();

    $results = array();
    foreach ($config as $code => $priority) {
      $priority['value'] = $code;
      $results[] = $priority;
    }

    return array('data' => $results);
  }

}
