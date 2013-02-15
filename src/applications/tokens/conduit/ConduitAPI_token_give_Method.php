<?php

final class ConduitAPI_token_give_Method extends ConduitAPI_token_Method {

  public function getMethodDescription() {
    return pht('Give or change a token.');
  }

  public function defineParamTypes() {
    return array(
      'tokenPHID'   => 'phid|null',
      'objectPHID'  => 'phid',
    );
  }

  public function defineErrorTypes() {
    return array();
  }

  public function defineReturnType() {
    return 'void';
  }

  public function execute(ConduitAPIRequest $request) {
    $editor = id(new PhabricatorTokenGivenEditor())
      ->setActor($request->getUser());

    if ($request->getValue('tokenPHID')) {
      $editor->addToken(
        $request->getValue('objectPHID'),
        $request->getValue('tokenPHID'));
    } else {
      $editor->deleteToken($request->getValue('objectPHID'));
    }
  }

}
