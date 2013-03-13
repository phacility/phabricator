<?php

/**
 * @group conduit
 */
final class ConduitAPI_phpast_version_Method
  extends ConduitAPI_phpast_Method {

  public function getMethodDescription() {
    return "Get server xhpast version.";
  }

  public function defineParamTypes() {
    return array();
  }

  public function defineReturnType() {
    return 'string';
  }

  public function defineErrorTypes() {
    return array(
      'ERR-NOT-FOUND' => 'xhpast was not found on the server',
      'ERR-COMMAND-FAILED' => 'xhpast died with a nonzero exit code',
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $path = xhpast_get_binary_path();
    if (!Filesystem::pathExists($path)) {
      throw new ConduitException('ERR-NOT-FOUND');
    }
    list($err, $stdout) = exec_manual('%s --version', $path);
    if ($err) {
      throw new ConduitException('ERR-COMMAND-FAILED');
    }
    return trim($stdout);
  }

}
