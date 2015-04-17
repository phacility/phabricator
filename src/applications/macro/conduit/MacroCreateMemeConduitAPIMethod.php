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
      'ERR-NOT-FOUND' => 'Macro was not found.',
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $user = $request->getUser();

    $macro_name = $request->getValue('macroName');
    $upper_text = $request->getValue('upperText');
    $lower_text = $request->getValue('lowerText');

    $uri = PhabricatorMacroMemeController::generateMacro(
      $user,
      $macro_name,
      $upper_text,
      $lower_text);

    if (!$uri) {
      throw new ConduitException('ERR-NOT-FOUND');
    }

    return array(
      'uri' => $uri,
    );
  }

}
