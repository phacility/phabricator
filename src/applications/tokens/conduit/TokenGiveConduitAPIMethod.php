<?php

final class TokenGiveConduitAPIMethod extends TokenConduitAPIMethod {

  public function getAPIMethodName() {
    return 'token.give';
  }

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
    $content_source = PhabricatorContentSource::newFromConduitRequest($request);

    $editor = id(new PhabricatorTokenGivenEditor())
      ->setActor($request->getUser())
      ->setContentSource($content_source);

    if ($request->getValue('tokenPHID')) {
      $editor->addToken(
        $request->getValue('objectPHID'),
        $request->getValue('tokenPHID'));
    } else {
      $editor->deleteToken($request->getValue('objectPHID'));
    }
  }

}
