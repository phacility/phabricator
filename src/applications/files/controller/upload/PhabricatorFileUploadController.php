<?php

/*
 * Copyright 2011 Facebook, Inc.
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

class PhabricatorFileUploadController extends PhabricatorFileController {

  public function processRequest() {

    $request = $this->getRequest();
    if ($request->isFormPost()) {
      $file = PhabricatorFile::newFromPHPUpload(
        idx($_FILES, 'file'),
        array(
          'name' => $request->getStr('name'),
        ));

      return id(new AphrontRedirectResponse())
        ->setURI('/file/info/'.phutil_escape_uri($file->getPHID()).'/');
    }

    $form = new AphrontFormView();
    $form->setAction('/file/upload/');

    $form
      ->setEncType('multipart/form-data')
      ->appendChild(
        id(new AphrontFormFileControl())
          ->setLabel('File')
          ->setName('file')
          ->setError(true))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('Name')
          ->setName('name')
          ->setCaption('Optional file display name.'))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue('Upload')
          ->addCancelButton('/file/'));

    $panel = new AphrontPanelView();
    $panel->setHeader('Upload File');

    $panel->appendChild($form);
    $panel->setWidth(AphrontPanelView::WIDTH_FORM);

    return $this->buildStandardPageResponse(
      array($panel),
      array(
        'title' => 'Upload File',
      ));
  }

}
