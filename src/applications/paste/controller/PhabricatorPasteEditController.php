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

final class PhabricatorPasteEditController extends PhabricatorPasteController {

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $paste = new PhabricatorPaste();
    $title = 'Create Paste';

    $parent_id = $request->getStr('parent');
    $parent = null;
    if ($parent_id) {
      // NOTE: If the Paste is forked from a paste which the user no longer
      // has permission to see, we still let them edit it.
      $parent = id(new PhabricatorPasteQuery())
        ->setViewer($user)
        ->withIDs(array($parent_id))
        ->execute();
      $parent = head($parent);

      if ($parent) {
        $paste->setParentPHID($parent->getPHID());
      }
    }

    $text = null;
    $e_text = true;
    $errors = array();
    if ($request->isFormPost()) {
      $text = $request->getStr('text');
      if (!strlen($text)) {
        $e_text = 'Required';
        $errors[] = 'The paste may not be blank.';
      } else {
        $e_text = null;
      }

      $paste->setTitle($request->getStr('title'));
      $paste->setLanguage($request->getStr('language'));

      if (!$errors) {
        $paste_file = PhabricatorFile::newFromFileData(
          $text,
          array(
            'name' => $title,
            'mime-type' => 'text/plain; charset=utf-8',
            'authorPHID' => $user->getPHID(),
          ));
        $paste->setFilePHID($paste_file->getPHID());
        $paste->setAuthorPHID($user->getPHID());
        $paste->save();

        return id(new AphrontRedirectResponse())->setURI($paste->getURI());
      }
    } else {
      if ($parent) {
        $paste->setTitle('Fork of '.$parent->getFullName());
        $paste->setLanguage($parent->getLanguage());

        $parent_file = id(new PhabricatorFile())->loadOneWhere(
          'phid = %s',
          $parent->getFilePHID());
        $text = $parent_file->loadFileData();
      }
    }

    $error_view = null;
    if ($errors) {
      $error_view = id(new AphrontErrorView())
        ->setTitle('A fatal omission!')
        ->setErrors($errors);
    }

    $form = new AphrontFormView();
    $form->setFlexible(true);

    $langs = array(
      '' => '(Detect With Wizardly Powers)',
    ) + PhabricatorEnv::getEnvConfig('pygments.dropdown-choices');

    $submit = id(new AphrontFormSubmitControl())
      ->setValue('Create Paste');

    $form
      ->setUser($user)
      ->addHiddenInput('parent', $parent_id)
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('Title')
          ->setValue($paste->getTitle())
          ->setName('title'))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel('Language')
          ->setName('language')
          ->setValue($paste->getLanguage())
          ->setOptions($langs))
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setLabel('Text')
          ->setError($e_text)
          ->setValue($text)
          ->setHeight(AphrontFormTextAreaControl::HEIGHT_VERY_TALL)
          ->setCustomClass('PhabricatorMonospaced')
          ->setName('text'))

    /* TODO: Doesn't have any useful options yet.
      ->appendChild(
        id(new AphrontFormPolicyControl())
          ->setLabel('Visible To')
          ->setUser($user)
          ->setValue(
            $new_paste->getPolicy(PhabricatorPolicyCapability::CAN_VIEW))
          ->setName('policy'))
    */

      ->appendChild($submit);

    $nav = $this->buildSideNavView();
    $nav->selectFilter('edit');
    $nav->appendChild(
      array(
        id(new PhabricatorHeaderView())->setHeader('Create Paste'),
        $error_view,
        $form,
      ));

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => $title,
        'device' => true,
      ));
  }

}
