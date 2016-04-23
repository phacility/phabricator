<?php

final class TokenGiveConduitAPIMethod extends TokenConduitAPIMethod {

  public function getAPIMethodName() {
    return 'token.give';
  }

  public function getMethodDescription() {
    return pht('Give or change a token.');
  }

  protected function defineParamTypes() {
    return array(
      'tokenPHID'   => 'phid|null',
      'objectPHID'  => 'phid',
    );
  }

  protected function defineReturnType() {
    return 'void';
  }

  protected function execute(ConduitAPIRequest $request) {
    $content_source = $request->newContentSource();

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
