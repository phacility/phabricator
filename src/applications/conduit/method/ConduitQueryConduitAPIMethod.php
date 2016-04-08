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

  public function getRequiredScope() {
    return self::SCOPE_ALWAYS;
  }

  protected function execute(ConduitAPIRequest $request) {
    $methods = id(new PhabricatorConduitMethodQuery())
      ->setViewer($request->getUser())
      ->execute();

    $map = array();
    foreach ($methods as $method) {
      $map[$method->getAPIMethodName()] = array(
        'description' => $method->getMethodDescription(),
        'params' => $method->getParamTypes(),
        'return' => $method->getReturnType(),
      );
    }

    return $map;
  }

}
