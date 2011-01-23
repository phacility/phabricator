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

class PhabricatorPHIDTypeEditController
  extends PhabricatorPHIDController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = idx($data, 'id');
  }

  public function processRequest() {

    if ($this->id) {
      $type = id(new PhabricatorPHIDType())->load($this->id);
      if (!$type) {
        return new Aphront404Response();
      }
    } else {
      $type = new PhabricatorPHIDType();
    }

    $e_type = true;
    $e_name = true;
    $errors = array();

    $request = $this->getRequest();
    if ($request->isFormPost()) {
      $type->setName($request->getStr('name'));
      if (!$type->getID()) {
        $type->setType($request->getStr('type'));
      }
      $type->setDescription($request->getStr('description'));

      if (!strlen($type->getType())) {
        $errors[] = 'Type code is required.';
        $e_type = 'Required';
      }

      if (!strlen($type->getName())) {
        $errors[] = 'Type name is required.';
        $e_name = 'Required';
      }

      if (!$errors) {
        $type->save();
        return id(new AphrontRedirectResponse())
          ->setURI('/phid/type/');
      }
    }

    $error_view = null;
    if ($errors) {
      $error_view = id(new AphrontErrorView())
        ->setTitle('Form Errors')
        ->setErrors($errors);
    }

    $form = new AphrontFormView();
    if ($type->getID()) {
      $form->setAction('/phid/type/edit/'.$type->getID().'/');
    } else {
      $form->setAction('/phid/type/edit/');
    }

    if ($type->getID()) {
      $type_immutable = true;
    } else {
      $type_immutable = false;
    }

    $form
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('Type')
          ->setName('type')
          ->setValue($type->getType())
          ->setError($e_type)
          ->setCaption(
            'Four character type identifier. This can not be changed once '.
            'it is created.')
          ->setDisabled($type_immutable))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('Name')
          ->setName('name')
          ->setValue($type->getName())
          ->setError($e_name))
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setLabel('Description')
          ->setName('description')
          ->setValue($type->getDescription()))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue('Save')
          ->addCancelButton('/phid/type/'));

    $panel = new AphrontPanelView();
    if ($type->getID()) {
      $panel->setHeader('Edit PHID Type');
    } else {
      $panel->setHeader('Create New PHID Type');
    }

    $panel->appendChild($form);
    $panel->setWidth(AphrontPanelView::WIDTH_FORM);

    return $this->buildStandardPageResponse(
      array($error_view, $panel),
      array(
        'title' => 'Edit PHID Type',
      ));
  }

}
