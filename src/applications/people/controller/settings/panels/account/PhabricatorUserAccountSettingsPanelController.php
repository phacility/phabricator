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

class PhabricatorUserAccountSettingsPanelController
  extends PhabricatorUserSettingsPanelController {

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();
    $editable = $this->getAccountEditable();

    $e_realname = true;
    $errors = array();
    if ($request->isFormPost()) {
      if (!$editable) {
        return new Aphront400Response();
      }

      if (!empty($_FILES['profile'])) {
        $err = idx($_FILES['profile'], 'error');
        if ($err != UPLOAD_ERR_NO_FILE) {
          $file = PhabricatorFile::newFromPHPUpload(
            $_FILES['profile'],
            array(
              'authorPHID' => $user->getPHID(),
            ));
          $okay = $file->isTransformableImage();
          if ($okay) {
            $xformer = new PhabricatorImageTransformer();
            $xformed = $xformer->executeProfileTransform(
              $file,
              $width = 50,
              $min_height = 50,
              $max_height = 50);
            $user->setProfileImagePHID($xformed->getPHID());
          } else {
            $errors[] =
              'Only valid image files (jpg, jpeg, png or gif) '.
              'will be accepted.';
          }
        }
      }

      $user->setRealName($request->getStr('realname'));

      if (!strlen($user->getRealName())) {
        $errors[] = 'Real name must be nonempty.';
        $e_realname = 'Required';
      }

      $new_timezone = $request->getStr('timezone');
      if (in_array($new_timezone, DateTimeZone::listIdentifiers(), true)) {
        $user->setTimezoneIdentifier($new_timezone);
      } else {
        $errors[] = 'The selected timezone is not a valid timezone.';
      }

      if (!$errors) {
        $user->save();
        return id(new AphrontRedirectResponse())
            ->setURI('/settings/page/account/?saved=true');
      }
    }

    $img_src = PhabricatorFileURI::getViewURIForPHID(
      $user->getProfileImagePHID());

    $notice = null;
    if (!$errors) {
      if ($request->getStr('saved')) {
        $notice = new AphrontErrorView();
        $notice->setSeverity(AphrontErrorView::SEVERITY_NOTICE);
        $notice->setTitle('Changes Saved');
        $notice->appendChild('<p>Your changes have been saved.</p>');
        $notice = $notice->render();
      }
    } else {
      $notice = new AphrontErrorView();
      $notice->setTitle('Form Errors');
      $notice->setErrors($errors);
      $notice = $notice->render();
    }

    $form = new AphrontFormView();
    $form
      ->setUser($user)
      ->setEncType('multipart/form-data')
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel('Username')
          ->setValue($user->getUsername()))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('Real Name')
          ->setName('realname')
          ->setError($e_realname)
          ->setValue($user->getRealName())
          ->setDisabled(!$editable))
      ->appendChild(
          id(new AphrontFormMarkupControl())
            ->setValue('<hr />'))
      ->appendChild(
        id(new AphrontFormMarkupControl())
          ->setLabel('Profile Image')
          ->setValue(
            phutil_render_tag(
              'img',
              array(
                'src' => $img_src,
              ))));

    if ($editable) {
      $timezone_ids = DateTimeZone::listIdentifiers();
      $timezone_id_map = array_combine($timezone_ids, $timezone_ids);

      $form
        ->appendChild(
          id(new AphrontFormFileControl())
            ->setLabel('Change Image')
            ->setName('profile'))
        ->appendChild(
            id(new AphrontFormMarkupControl())
              ->setValue('<hr />'))
        ->appendChild(
          id(new AphrontFormSelectControl())
            ->setLabel('Timezone')
            ->setName('timezone')
            ->setOptions($timezone_id_map)
            ->setValue($user->getTimezoneIdentifier()))
        ->appendChild(
            id(new AphrontFormMarkupControl())
              ->setValue('<hr />'))
        ->appendChild(
          id(new AphrontFormSubmitControl())
            ->setValue('Save'));
    }

    $panel = new AphrontPanelView();
    $panel->setHeader('Profile Settings');
    $panel->setWidth(AphrontPanelView::WIDTH_FORM);
    $panel->appendChild($form);

    return id(new AphrontNullView())
      ->appendChild(
        array(
          $notice,
          $panel,
        ));
  }
}
