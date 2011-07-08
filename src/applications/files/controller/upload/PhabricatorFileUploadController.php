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
    $user = $request->getUser();

    if ($request->isFormPost()) {
      $files = $request->getArr('file');

      if (count($files) > 1) {
        return id(new AphrontRedirectResponse())
          ->setURI('/file/?author='.phutil_escape_uri($user->getUserName()));
      } else {
        return id(new AphrontRedirectResponse())
          ->setURI('/file/info/'.end($files).'/');
      }
    }

    $panel_id = celerity_generate_unique_node_id();

    $form = new AphrontFormView();
    $form->setAction('/file/upload/');
    $form->setUser($request->getUser());

    $form
      ->setEncType('multipart/form-data')

      ->appendChild(
        id(new AphrontFormDragAndDropUploadControl())
        ->setLabel('Files')
        ->setName('file')
        ->setError(true)
          ->setDragAndDropTarget($panel_id)
          ->setActivatedClass('aphront-panel-view-drag-and-drop'))

      ->appendChild(
        id(new AphrontFormSubmitControl())
        ->setValue('Done here!'));

    $panel = new AphrontPanelView();
    $panel->setHeader('Upload File');

    $panel->appendChild($form);
    $panel->setWidth(AphrontPanelView::WIDTH_FORM);
    $panel->setID($panel_id);

    return $this->buildStandardPageResponse(
      array($panel),
      array(
        'title' => 'Upload File',
      ));
  }

}
