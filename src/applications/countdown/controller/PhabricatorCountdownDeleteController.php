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

final class PhabricatorCountdownDeleteController
  extends PhabricatorCountdownController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    $timer = id(new PhabricatorTimer())->load($this->id);
    if (!$timer) {
      return new Aphront404Response();
    }

    if (($timer->getAuthorPHID() !== $user->getPHID())
        && $user->getIsAdmin() === false) {
      return new Aphront403Response();
    }

    if ($request->isFormPost()) {
      $timer->delete();
      return id(new AphrontRedirectResponse())
        ->setURI('/countdown/');
    }

    $dialog = new AphrontDialogView();
    $dialog->setUser($request->getUser());
    $dialog->setTitle('Really delete this countdown?');
    $dialog->appendChild(
      '<p>Are you sure you want to delete the countdown "'.
      phutil_escape_html($timer->getTitle()).'"?</p>');
    $dialog->addSubmitButton('Delete');
    $dialog->addCancelButton('/countdown/');
    $dialog->setSubmitURI($request->getPath());

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

}
