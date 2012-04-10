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
 * @group maniphest
 */
final class ManiphestSavedQueryEditController extends ManiphestController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = idx($data, 'id');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $key = $request->getStr('key');
    if (!$key) {
      $id = nonempty($this->id, $request->getInt('id'));
      if (!$id) {
        return new Aphront404Response();
      }
      $query = id(new ManiphestSavedQuery())->load($id);
      if (!$query) {
        return new Aphront404Response();
      }
      if ($query->getUserPHID() != $user->getPHID()) {
        return new Aphront400Response();
      }
    } else {
      $query = new ManiphestSavedQuery();
      $query->setUserPHID($user->getPHID());
      $query->setQueryKey($key);
      $query->setIsDefault(0);
    }

    $e_name = true;
    $errors = array();

    if ($request->isFormPost()) {
      $e_name = null;
      $query->setName($request->getStr('name'));
      if (!strlen($query->getName())) {
        $e_name = 'Required';
        $errors[] = 'Saved query name is required.';
      }

      if (!$errors) {
        $query->save();
        return id(new AphrontRedirectResponse())->setURI('/maniphest/custom/');
      }
    }

    if ($errors) {
      $error_view = new AphrontErrorView();
      $error_view->setTitle('Form Errors');
      $error_view->setErrors($errors);
    } else {
      $error_view = null;
    }

    if ($query->getID()) {
      $header = 'Edit Saved Query';
      $cancel_uri = '/maniphest/custom/';
    } else {
      $header = 'New Saved Query';
      $cancel_uri = '/maniphest/view/custom/?key='.$key;
    }

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->addHiddenInput('key', $key)
      ->addHiddenInput('id',  $query->getID())
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('Name')
          ->setValue($query->getName())
          ->setName('name')
          ->setError($e_name))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->addCancelButton($cancel_uri)
          ->setValue('Save'));

    $panel = new AphrontPanelView();
    $panel->setHeader($header);
    $panel->appendChild($form);
    $panel->setWidth(AphrontPanelView::WIDTH_FORM);

    $nav = $this->buildBaseSideNav();
    // The side nav won't show "Saved Queries..." until you have at least one.
    $nav->selectFilter('saved', 'custom');
    $nav->appendChild($error_view);
    $nav->appendChild($panel);

    return $this->buildStandardPageResponse(
      $nav,
      array(
        'title' => 'Saved Queries',
      ));
  }

}
