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

final class PhabricatorFileDeleteController extends PhabricatorFileController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    $file = id(new PhabricatorFile())->loadOneWhere(
      'id = %d',
      $this->id);
    if (!$file) {
      return new Aphront404Response();
    }

    if (($user->getPHID() != $file->getAuthorPHID()) &&
        (!$user->getIsAdmin())) {
      return new Aphront403Response();
    }

    if ($request->isFormPost()) {
      $file->delete();
      return id(new AphrontRedirectResponse())->setURI('/file/');
    }

    $dialog = new AphrontDialogView();
    $dialog->setUser($user);
    $dialog->setTitle('Really delete file?');
    $dialog->appendChild(
      "<p>Permanently delete '".phutil_escape_html($file->getName())."'? This ".
      "action can not be undone.");
    $dialog->addSubmitButton('Delete');
    $dialog->addCancelButton($file->getInfoURI());

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }
}
