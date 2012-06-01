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

final class PhabricatorFileUploadController extends PhabricatorFileController {

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    if ($request->isFormPost()) {
      $file = PhabricatorFile::newFromPHPUpload(
        idx($_FILES, 'file'),
        array(
          'name'        => $request->getStr('name'),
          'authorPHID'  => $user->getPHID(),
        ));

      return id(new AphrontRedirectResponse())->setURI($file->getBestURI());
    }

    $panel = new PhabricatorFileUploadView();
    $panel->setUser($user);

    return $this->buildStandardPageResponse(
      array($panel),
      array(
        'title' => 'Upload File',
      ));
  }
}
