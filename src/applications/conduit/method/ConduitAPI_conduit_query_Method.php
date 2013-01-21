<?php

/**
 * @group conduit
 */
final class ConduitAPI_conduit_query_Method extends ConduitAPIMethod {

  public function getMethodDescription() {
    return "Returns the parameters of the Conduit methods.";
  }

  public function defineParamTypes() {
    return array();
  }

  public function defineReturnType() {
    return 'dict<dict>';
  }

  public function defineErrorTypes() {
    return array();
  }

  protected function execute(ConduitAPIRequest $request) {
    $classes = id(new PhutilSymbolLoader())
      ->setAncestorClass('ConduitAPIMethod')
      ->setType('class')
      ->setConcreteOnly(true)
      ->selectSymbolsWithoutLoading();

    $names_to_params = array();
    foreach ($classes as $class) {
      $method_name = $class["name"];
      $obj = newv($method_name, array());
      $names_to_params[$this->getAPIMethodNameFromClassName($method_name)] =
        array("params" => $obj->defineParamTypes());
    }
    return $names_to_params;
  }

}
