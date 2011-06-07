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

class PhabricatorPasteCreateController extends PhabricatorPasteController {

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    $form = new AphrontFormView();
    $form
      ->setUser($user)
      ->setAction($request->getRequestURI()->getPath())
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('Title')
          ->setName('title'))
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setLabel('Text')
          ->setName('text'))
      ->appendChild(
                    id(new AphrontFormSubmitControl())
                    ->addCancelButton('/paste/')
                    ->setValue('Create Paste'));
    
    $panel = new AphrontPanelView();
    $panel->setWidth(AphrontPanelView::WIDTH_FULL);
    $panel->setHeader("Create a Paste");
    $panel->appendChild($form);

    return $this->buildStandardPageResponse(
      $panel,
      array(
        'title' => 'Paste Creation',
        'tab' => 'create',
      ));
  }
}