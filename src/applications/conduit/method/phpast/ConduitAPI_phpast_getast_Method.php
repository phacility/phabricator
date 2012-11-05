<?php

/**
 * @group conduit
 */
final class ConduitAPI_phpast_getast_Method extends ConduitAPIMethod {

  public function getMethodDescription() {
    return "Parse a piece of PHP code.";
  }

  public function defineParamTypes() {
    return array(
      'code' => 'required string',
    );
  }

  public function defineReturnType() {
    return 'nonempty dict';
  }

  public function defineErrorTypes() {
    return array(
      'ERR-XHPAST-LEY' => 'xhpast got Rickrolled',
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $source = $request->getValue('code');
    $future = xhpast_get_parser_future($source);
    list($stdout) = $future->resolvex();

    return json_decode($stdout, true);
  }

}
