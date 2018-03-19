<?php

final class MacroCreateMemeConduitAPIMethod extends MacroConduitAPIMethod {

  public function getAPIMethodName() {
    return 'macro.creatememe';
  }

  public function getMethodStatus() {
    return self::METHOD_STATUS_UNSTABLE;
  }

  public function getMethodDescription() {
    return pht('Generate a meme.');
  }

  protected function defineParamTypes() {
    return array(
      'macroName'    => 'string',
      'upperText'    => 'optional string',
      'lowerText'    => 'optional string',
    );
  }

  protected function defineReturnType() {
    return 'string';
  }

  protected function defineErrorTypes() {
    return array(
      'ERR-NOT-FOUND' => pht('Macro was not found.'),
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $user = $request->getUser();

    $file = id(new PhabricatorMemeEngine())
      ->setViewer($user)
      ->setTemplate($request->getValue('macroName'))
      ->setAboveText($request->getValue('upperText'))
      ->setBelowText($request->getValue('lowerText'))
      ->newAsset();

    return array(
      'uri' => $file->getViewURI(),
    );
  }

}
