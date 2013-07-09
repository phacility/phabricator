<?php

/**
 * @group conduit
 */
final class ConduitAPI_macro_create_Method extends ConduitAPI_macro_Method {

  public function getMethodDescription() {
    return "Generate a macro.";
  }

  public function defineParamTypes() {
    return array(
      'macro_name'    => 'string',
      'upper_text'    => 'string',
      'lower_text'    => 'string',
    );
  }

  public function defineReturnType() {
    return 'string';
  }

  public function defineErrorTypes() {
    return array(
      'ERR_NOT_FOUND' => 'Macro was not found.',
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $user = $request->getUser();

    $macro_name = $request->getValue('macro_name');
    $upper_text = $request->getValue('upper_text');
    $lower_text = $request->getValue('lower_text');

    $uri = PhabricatorMacroMemeController::generateMacro($user, $macro_name, $upper_text, $lower_text);
    if (!$uri) {
      throw new ConduitException('ERR_NOT_FOUND');
    }

    return $uri;
  }

}
