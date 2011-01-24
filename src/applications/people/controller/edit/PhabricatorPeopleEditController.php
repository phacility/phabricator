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

class PhabricatorPeopleEditController extends PhabricatorPeopleController {

  private $username;

  public function willProcessRequest(array $data) {
    $this->username = idx($data, 'username');
  }

  public function processRequest() {

    if ($this->username) {
      $user = id(new PhabricatorUser())->loadOneWhere(
        'userName = %s',
        $this->username);
      if (!$user) {
        return new Aphront404Response();
      }
    } else {
      $user = new PhabricatorUser();
    }

    $e_username = true;
    $e_realname = true;
    $e_email    = true;
    $errors = array();

    $request = $this->getRequest();
    if ($request->isFormPost()) {
      if (!$user->getID()) {
        $user->setUsername($request->getStr('username'));
      }
      $user->setRealName($request->getStr('realname'));
      $user->setEmail($request->getStr('email'));

      if (!strlen($user->getUsername())) {
        $errors[] = "Username is required.";
        $e_username = 'Required';
      } else if (!preg_match('/^[a-z0-9]+$/', $user->getUsername())) {
        $errors[] = "Username must consist of only numbers and letters.";
        $e_username = 'Invalid';
      }

      if (!strlen($user->getRealName())) {
        $errors[] = 'Real name is required.';
        $e_realname = 'Required';
      }

      if (!strlen($user->getEmail())) {
        $errors[] = 'Email is required.';
        $e_email = 'Required';
      }

      if (!$errors) {
        $user->save();
        $response = id(new AphrontRedirectResponse())
          ->setURI('/p/'.$user->getUsername().'/');
        return $response;
      }
    }

    $error_view = null;
    if ($errors) {
      $error_view = id(new AphrontErrorView())
        ->setTitle('Form Errors')
        ->setErrors($errors);
    }

    $form = new AphrontFormView();
    if ($user->getUsername()) {
      $form->setAction('/people/edit/'.$user->getUsername().'/');
    } else {
      $form->setAction('/people/edit/');
    }

    if ($user->getID()) {
      $is_immutable = true;
    } else {
      $is_immutable = false;
    }

    $form
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('Username')
          ->setName('username')
          ->setValue($user->getUsername())
          ->setError($e_username)
          ->setDisabled($is_immutable)
          ->setCaption('Usernames are permanent and can not be changed later!'))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('Real Name')
          ->setName('realname')
          ->setValue($user->getRealName())
          ->setError($e_realname))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('Email')
          ->setName('email')
          ->setValue($user->getEmail())
          ->setError($e_email))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue('Save')
          ->addCancelButton('/people/'));

    $panel = new AphrontPanelView();
    if ($user->getID()) {
      $panel->setHeader('Edit User');
    } else {
      $panel->setHeader('Create New User');
    }

    $panel->appendChild($form);
    $panel->setWidth(AphrontPanelView::WIDTH_FORM);

    return $this->buildStandardPageResponse(
      array($error_view, $panel),
      array(
        'title' => 'Edit User',
      ));
  }

}
