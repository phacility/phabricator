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

class PhabricatorPeopleProfileEditController
  extends PhabricatorPeopleController {

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    $profile = id(new PhabricatorUserProfile())->loadOneWhere(
      'userPHID = %s',
      $user->getPHID());
    if (!$profile) {
      $profile = new PhabricatorUserProfile();
      $profile->setUserPHID($user->getPHID());
    }


    if ($request->isFormPost()) {
      $profile->setTitle($request->getStr('title'));
      $profile->setBlurb($request->getStr('blurb'));

      if (!empty($_FILES['image'])) {
        $err = idx($_FILES['image'], 'error');
        if ($err != UPLOAD_ERR_NO_FILE) {
          $file = PhabricatorFile::newFromPHPUpload($_FILES['image']);
          $profile->setProfileImagePHID($file->getPHID());
        }
      }

      $profile->save();
      $response = id(new AphrontRedirectResponse())
        ->setURI('/p/'.$user->getUsername().'/');
      return $response;
    }

    $form = new AphrontFormView();
    $form
      ->setUser($request->getUser())
      ->setAction('/profile/edit/')
      ->setEncType('multipart/form-data')
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('Title')
          ->setName('title')
          ->setValue($profile->getTitle())
          ->setCaption('Serious business title.'))
      ->appendChild(
        '<p class="aphront-form-instructions">Write something about yourself! '.
        'Make sure to include <strong>important information</strong> like '.
        'your <strong>favorite pokemon</strong> and which '.
        '<strong>Starcraft race</strong> you play.</p>')
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setLabel('Blurb')
          ->setName('blurb')
          ->setValue($profile->getBlurb()))
      ->appendChild(
        id(new AphrontFormFileControl())
          ->setLabel('Change Image')
          ->setName('image')
          ->setCaption('Upload a 280px-wide image.'))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue('Save')
          ->addCancelButton('/p/'.$user->getUsername().'/'));

    $panel = new AphrontPanelView();
    $panel->setHeader('Edit Profile Details');
    $panel->appendChild($form);
    $panel->setWidth(AphrontPanelView::WIDTH_FORM);

    return $this->buildStandardPageResponse(
      $panel,
      array(
        'title' => 'Edit Profile',
      ));
  }

}
