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

final class PhabricatorDirectoryCategoryEditController
  extends PhabricatorDirectoryController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = idx($data, 'id');
  }

  public function processRequest() {

    if ($this->id) {
      $category = id(new PhabricatorDirectoryCategory())->load($this->id);
      if (!$category) {
        return new Aphront404Response();
      }
    } else {
      $category = new PhabricatorDirectoryCategory();
    }

    $e_name = true;
    $errors = array();

    $request = $this->getRequest();
    if ($request->isFormPost()) {
      $category->setName($request->getStr('name'));
      $category->setSequence($request->getStr('sequence'));

      if (!strlen($category->getName())) {
        $errors[] = 'Category name is required.';
        $e_name = 'Required';
      }

      if (!$errors) {
        $category->save();
        return id(new AphrontRedirectResponse())
          ->setURI('/directory/edit/');
      }
    }

    $error_view = null;
    if ($errors) {
      $error_view = id(new AphrontErrorView())
        ->setTitle('Form Errors')
        ->setErrors($errors);
    }

    $form = new AphrontFormView();
    $form->setUser($request->getUser());
    if ($category->getID()) {
      $form->setAction('/directory/category/edit/'.$category->getID().'/');
    } else {
      $form->setAction('/directory/category/edit/');
    }

    $categories = id(new PhabricatorDirectoryCategory())->loadAll();
    $category_map = mpull($categories, 'getName', 'getID');

    $form
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('Name')
          ->setName('name')
          ->setValue($category->getName())
          ->setError($e_name))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('Order')
          ->setName('sequence')
          ->setValue((int)$category->getSequence()))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue('Save')
          ->addCancelButton('/directory/edit/'));

    $panel = new AphrontPanelView();
    if ($category->getID()) {
      $panel->setHeader('Edit Directory Category');
    } else {
      $panel->setHeader('Create New Directory Category');
    }

    $panel->appendChild($form);
    $panel->setWidth(AphrontPanelView::WIDTH_FORM);

    return $this->buildStandardPageResponse(
      array($error_view, $panel),
      array(
        'title' => 'Edit Directory Category',
      ));
  }

}
