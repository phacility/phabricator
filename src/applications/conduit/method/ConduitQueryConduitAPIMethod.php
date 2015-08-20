<?php

final class ConduitQueryConduitAPIMethod extends ConduitAPIMethod {

  public function getAPIMethodName() {
    return 'conduit.query';
  }

  public function getMethodDescription() {
    return pht('Returns the parameters of the Conduit methods.');
  }

  protected function defineParamTypes() {
    return array();
  }

  protected function defineReturnType() {
    return 'dict<dict>';
  }

  protected function execute(ConduitAPIRequest $request) {
    $classes = id(new PhutilClassMapQuery())
      ->setAncestorClass('ConduitAPIMethod')
      ->execute();

    $names_to_params = array();
    foreach ($classes as $class) {
      $names_to_params[$class->getAPIMethodName()] = array(
        'description' => $class->getMethodDescription(),
        'params' => $class->getParamTypes(),
        'return' => $class->getReturnType(),
      );
    }

    return $names_to_params;
  }

}
