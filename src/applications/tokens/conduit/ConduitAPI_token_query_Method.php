<?php

final class ConduitAPI_token_query_Method extends ConduitAPI_token_Method {

  public function getMethodDescription() {
    return pht('Query tokens.');
  }

  public function defineParamTypes() {
    return array();
  }

  public function defineReturnType() {
    return 'list<dict>';
  }

  public function defineErrorTypes() {
    return array();
  }

  public function execute(ConduitAPIRequest $request) {
    $query = id(new PhabricatorTokenQuery())
      ->setViewer($request->getUser());

    $tokens = $query->execute();

    return $this->buildTokenDicts($tokens);
  }

}
