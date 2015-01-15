<?php

final class TokenQueryConduitAPIMethod extends TokenConduitAPIMethod {

  public function getAPIMethodName() {
    return 'token.query';
  }

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

  protected function execute(ConduitAPIRequest $request) {
    $query = id(new PhabricatorTokenQuery())
      ->setViewer($request->getUser());

    $tokens = $query->execute();

    return $this->buildTokenDicts($tokens);
  }

}
