<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * @group conduit
 */
final class ConduitAPI_phpast_version_Method extends ConduitAPIMethod {

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
