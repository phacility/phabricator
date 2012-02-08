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

class PhabricatorDirectoryItemEditController
  extends PhabricatorDirectoryController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = idx($data, 'id');
  }

  public function processRequest() {

    if ($this->id) {
      $item = id(new PhabricatorDirectoryItem())->load($this->id);
      if (!$item) {
        return new Aphront404Response();
      }
    } else {
      $item = new PhabricatorDirectoryItem();
    }

    $e_name = true;
    $e_href = true;
    $errors = array();

    $request = $this->getRequest();
    if ($request->isFormPost()) {
      $item->setName($request->getStr('name'));
      $item->setHref($request->getStr('href'));
      $item->setDescription($request->getStr('description'));
      $item->setCategoryID($request->getStr('categoryID'));
      $item->setSequence($request->getStr('sequence'));

      if (!strlen($item->getName())) {
        $errors[] = 'Item name is required.';
        $e_name = 'Required';
      }

      if (!strlen($item->getHref())) {
        $errors[] = 'Item link is required.';
        $e_href = 'Required';
      } else {
        $href = $item->getHref();
        if (!PhabricatorEnv::isValidWebResource($href)) {
          $e_href = 'Invalid';
          $errors[] = 'Item link must point to a valid web page.';
        }
      }

      if (!$errors) {
        $item->save();
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

    if ($item->getID()) {
      $form->setAction('/directory/item/edit/'.$item->getID().'/');
    } else {
      $form->setAction('/directory/item/edit/');
    }

    $categories = id(new PhabricatorDirectoryCategory())->loadAll();
    $category_map = mpull($categories, 'getName', 'getID');

    $form
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('Name')
          ->setName('name')
          ->setValue($item->getName())
          ->setError($e_name))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel('Category')
          ->setName('categoryID')
          ->setOptions($category_map)
          ->setValue($item->getCategoryID()))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('Link')
          ->setName('href')
          ->setValue($item->getHref())
          ->setError($e_href))
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setLabel('Description')
          ->setName('description')
          ->setValue($item->getDescription()))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('Order')
          ->setName('sequence')
          ->setCaption(
            'Items in a category are sorted by "order", then by name.')
          ->setValue((int)$item->getSequence()))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue('Save')
          ->addCancelButton('/directory/edit/'));

    $panel = new AphrontPanelView();
    if ($item->getID()) {
      $panel->setHeader('Edit Directory Item');
    } else {
      $panel->setHeader('Create New Directory Item');
    }

    $panel->appendChild($form);
    $panel->setWidth(AphrontPanelView::WIDTH_FORM);

    return $this->buildStandardPageResponse(
      array($error_view, $panel),
      array(
        'title' => 'Edit Directory Item',
      ));
  }

}
