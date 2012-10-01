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

final class PhabricatorMacroDeleteController
  extends PhabricatorMacroController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {

    $macro = id(new PhabricatorFileImageMacro())->load($this->id);
    if (!$macro) {
      return new Aphront404Response();
    }

    $request = $this->getRequest();

    if ($request->isDialogFormPost()) {
      $macro->delete();
      return id(new AphrontRedirectResponse())->setURI(
        $this->getApplicationURI());
    }

    $dialog = new AphrontDialogView();
    $dialog
      ->setUser($request->getUser())
      ->setTitle('Really delete macro?')
      ->appendChild(
        '<p>Really delete the much-beloved image macro "'.
        phutil_escape_html($macro->getName()).'"? It will be sorely missed.'.
        '</p>')
      ->setSubmitURI($this->getApplicationURI('/delete/'.$this->id.'/'))
      ->addSubmitButton('Delete')
      ->addCancelButton($this->getApplicationURI());


    return id(new AphrontDialogResponse())->setDialog($dialog);
  }
}
