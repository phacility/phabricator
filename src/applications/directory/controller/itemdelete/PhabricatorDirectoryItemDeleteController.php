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

final class PhabricatorDirectoryItemDeleteController
  extends PhabricatorDirectoryController {

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {

    $item = id(new PhabricatorDirectoryItem())->load($this->id);
    if (!$item) {
      return new Aphront404Response();
    }

    $request = $this->getRequest();

    if ($request->isFormPost()) {
      $item->delete();
      return id(new AphrontRedirectResponse())
        ->setURI('/directory/edit/');
    }

    $dialog = new AphrontDialogView();
    $dialog->setUser($request->getUser());
    $dialog->setTitle('Really delete this item?');
    $dialog->appendChild("Are you sure you want to delete this item?");
    $dialog->addSubmitButton('Delete');
    $dialog->addCancelButton('/directory/edit/');
    $dialog->setSubmitURI($request->getPath());

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

}
