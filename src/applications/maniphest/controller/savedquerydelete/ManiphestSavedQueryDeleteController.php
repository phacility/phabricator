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
 * @group maniphest
 */
final class ManiphestSavedQueryDeleteController extends ManiphestController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $id = $this->id;
    $query = id(new ManiphestSavedQuery())->load($id);
    if (!$query) {
      return new Aphront404Response();
    }
    if ($query->getUserPHID() != $user->getPHID()) {
      return new Aphront400Response();
    }

    if ($request->isDialogFormPost()) {
      $query->delete();
      return id(new AphrontRedirectResponse())->setURI('/maniphest/custom/');
    }

    $name = $query->getName();

    $dialog = id(new AphrontDialogView())
      ->setUser($user)
      ->setTitle('Really delete this query?')
      ->appendChild(
        '<p>'.
          'Really delete the query "'.phutil_escape_html($name).'"? '.
          'It will be lost forever!'.
        '</p>')
      ->addCancelButton('/maniphest/custom/')
      ->addSubmitButton('Delete');

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

}
