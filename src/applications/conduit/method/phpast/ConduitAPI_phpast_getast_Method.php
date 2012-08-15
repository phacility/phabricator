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
