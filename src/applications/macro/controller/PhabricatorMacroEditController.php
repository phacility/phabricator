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

final class PhabricatorMacroEditController
  extends PhabricatorMacroController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = idx($data, 'id');
  }

  public function processRequest() {

    if ($this->id) {
      $macro = id(new PhabricatorFileImageMacro())->load($this->id);
      if (!$macro) {
        return new Aphront404Response();
      }
    } else {
      $macro = new PhabricatorFileImageMacro();
    }

    $errors = array();
    $e_name = true;
    $file = null;

    $request = $this->getRequest();
    $user = $request->getUser();
    if ($request->isFormPost()) {

      $macro->setName($request->getStr('name'));

      if (!strlen($macro->getName())) {
        $errors[] = 'Macro name is required.';
        $e_name = 'Required';
      } else if (!preg_match('/^[a-z0-9_-]{3,}$/', $macro->getName())) {
        $errors[] = 'Macro must be at least three characters long and contain '.
                    'only lowercase letters, digits, hyphen and underscore.';
        $e_name = 'Invalid';
      } else {
        $e_name = null;
      }

      if (!$errors) {

        $file = PhabricatorFile::newFromPHPUpload(
          idx($_FILES, 'file'),
          array(
            'name' => $request->getStr('name'),
            'authorPHID' => $user->getPHID(),
          ));
        $macro->setFilePHID($file->getPHID());

        try {
          $macro->save();
          return id(new AphrontRedirectResponse())->setURI(
            $this->getApplicationURI());
        } catch (AphrontQueryDuplicateKeyException $ex) {
          $errors[] = 'Macro name is not unique!';
          $e_name = 'Duplicate';
        }
      }
    } else if ($this->id) {
      $file = id(new PhabricatorFile())
        ->loadOneWhere('phid = %s', $macro->getFilePHID());
    }

    $caption = null;
    if ($file) {
      $caption = phutil_render_tag(
        'img',
        array(
          'src' => $file->getViewURI(),
        ));
    }

    if ($errors) {
      $error_view = new AphrontErrorView();
      $error_view->setTitle('Form Errors');
      $error_view->setErrors($errors);
    } else {
      $error_view = null;
    }

    $form = new AphrontFormView();
    $form->setUser($request->getUser());

    $form
      ->setEncType('multipart/form-data')
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('Name')
          ->setName('name')
          ->setValue($macro->getName())
          ->setCaption('This word or phrase will be replaced with the image.')
          ->setError($e_name))
      ->appendChild(
        id(new AphrontFormFileControl())
          ->setLabel('File')
          ->setName('file')
          ->setCaption($caption)
          ->setError(true))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue('Save Image Macro')
          ->addCancelButton($this->getApplicationURI()));

    $panel = new AphrontPanelView();
    if ($macro->getID()) {
      $title = 'Edit Image Macro';
    } else {
      $title = 'Create Image Macro';
    }
    $panel->setHeader($title);
    $panel->appendChild($form);
    $panel->setWidth(AphrontPanelView::WIDTH_FULL);

    $nav = $this->buildSideNavView($macro);
    $nav->selectFilter('#', 'edit');
    $nav->appendChild($error_view);
    $nav->appendChild($panel);

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => $title,
      ));
  }
}
