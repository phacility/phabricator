<?php

/**
 * @group conduit
 */
final class ConduitAPI_macro_query_Method extends ConduitAPI_macro_Method {

  public function getMethodDescription() {
    return "Retrieve image macro information.";
  }

  public function defineParamTypes() {
    return array(
    );
  }

  public function defineReturnType() {
    return 'list<dict>';
  }

  public function defineErrorTypes() {
    return array(
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $macros = id(new PhabricatorMacroQuery())
      ->setViewer($request->getUser())
      ->execute();

    $results = array();
    foreach ($macros as $macro) {
      $results[$macro->getName()] = array(
        'uri' => $macro->getFile()->getBestURI(),
      );
    }

    return $results;
  }

}
