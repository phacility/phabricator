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
    $paste = new PhabricatorPaste();

    $error_view = null;
    $e_text = true;

    if ($request->isFormPost()) {
      $errors = array();
      $title = $request->getStr('title');
      $text = $request->getStr('text');

      if (!strlen($text)) {
        $e_text = 'Required';
        $errors[] = 'The paste may not be blank.';
      } else {
        $e_text = null;
      }

      $paste->setTitle($title);

      if (!$errors) {
        $paste_file = PhabricatorFile::newFromFileData(
          $text,
          array(
            'name' => $title,
            'mime-type' => 'text/plain; charset=utf-8',
        ));
        $paste->setFilePHID($paste_file->getPHID());
        $paste->setAuthorPHID($user->getPHID());
        $paste->save();

        return id(new AphrontRedirectResponse())
          ->setURI('/P'.$paste->getID());
      } else {
        $error_view = new AphrontErrorView();
        $error_view->setErrors($errors);
        $error_view->setTitle('A problem has occurred!');
      }
    }

    $form = new AphrontFormView();
    $form
      ->setUser($user)
      ->setAction($request->getRequestURI()->getPath())
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('Title')
          ->setValue($paste->getTitle())
          ->setName('title'))
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setLabel('Text')
          ->setError($e_text)
          ->setName('text'))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->addCancelButton('/paste/')
          ->setValue('Create Paste'));

    $panel = new AphrontPanelView();
    $panel->setWidth(AphrontPanelView::WIDTH_FORM);
    $panel->setHeader('Create a Paste');
    $panel->appendChild($form);

    return $this->buildStandardPageResponse(
      array(
        $error_view,
        $panel,
      ),
      array(
        'title' => 'Paste Creation',
        'tab' => 'create',
      ));
  }
}
