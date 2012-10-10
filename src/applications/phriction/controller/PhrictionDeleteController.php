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
 * @group phriction
 */
final class PhrictionDeleteController extends PhrictionController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    $document = id(new PhrictionDocument())->load($this->id);
    if (!$document) {
      return new Aphront404Response();
    }

    $document_uri = PhrictionDocument::getSlugURI($document->getSlug());

    if ($request->isFormPost()) {
        $editor = id(PhrictionDocumentEditor::newForSlug($document->getSlug()))
          ->setActor($user)
          ->delete();
        return id(new AphrontRedirectResponse())->setURI($document_uri);
    }

    $dialog = id(new AphrontDialogView())
      ->setUser($user)
      ->setTitle('Delete document?')
      ->appendChild(
        'Really delete this document? You can recover it later by reverting '.
        'to a previous version.')
      ->addSubmitButton('Delete')
      ->addCancelButton($document_uri);

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

}
