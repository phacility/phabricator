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

final class PhabricatorOwnersDeleteController
  extends PhabricatorOwnersController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $package = id(new PhabricatorOwnersPackage())->load($this->id);
    if (!$package) {
      return new Aphront404Response();
    }

    if ($request->isDialogFormPost()) {
      $package->delete();
      return id(new AphrontRedirectResponse())->setURI('/owners/');
    }

    $dialog = id(new AphrontDialogView())
      ->setUser($user)
      ->setTitle('Really delete this package?')
      ->appendChild(
        '<p>Are you sure you want to delete the "'.
        phutil_escape_html($package->getName()).'" package? This operation '.
        'can not be undone.</p>')
      ->addSubmitButton('Delete')
      ->addCancelButton('/owners/package/'.$package->getID().'/')
      ->setSubmitURI($request->getRequestURI());

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

}
