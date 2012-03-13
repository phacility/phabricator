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
final class ConduitAPI_file_upload_Method extends ConduitAPIMethod {

  public function getMethodDescription() {
    return "Upload a file to the server.";
  }

  public function defineParamTypes() {
    return array(
      'data_base64' => 'required nonempty base64-bytes',
      'name' => 'optional string',
    );
  }

  public function defineReturnType() {
    return 'nonempty guid';
  }

  public function defineErrorTypes() {
    return array(
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $data = $request->getValue('data_base64');
    $name = $request->getValue('name');
    $data = base64_decode($data, $strict = true);
    $user = $request->getUser();

    $file = PhabricatorFile::newFromFileData(
      $data,
      array(
        'name' => $name,
        'authorPHID' => $user->getPHID(),
      ));
    return $file->getPHID();
  }

}
