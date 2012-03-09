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

final class PhabricatorFileDropUploadController
  extends PhabricatorFileController {

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    // NOTE: Throws if valid CSRF token is not present in the request.
    $request->validateCSRF();

    $data = file_get_contents('php://input');
    $name = $request->getStr('name');

    $file = PhabricatorFile::newFromFileData(
      $data,
      array(
        'name' => $request->getStr('name'),
        'authorPHID' => $user->getPHID(),
      ));

    $view = new AphrontAttachedFileView();
    $view->setFile($file);

    return id(new AphrontAjaxResponse())->setContent(
      array(
        'id'   => $file->getID(),
        'phid' => $file->getPHID(),
        'html' => $view->render(),
        'uri'  => $file->getBestURI(),
      ));
  }

}
