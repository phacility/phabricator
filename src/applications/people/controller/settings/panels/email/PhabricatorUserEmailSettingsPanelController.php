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

final class PhabricatorUserEmailSettingsPanelController
  extends PhabricatorUserSettingsPanelController {

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();
    $editable = $this->getAccountEditable();

    $e_email = true;
    $errors = array();
    if ($request->isFormPost()) {
      if (!$editable) {
        return new Aphront400Response();
      }

      $user->setEmail($request->getStr('email'));

      if (!strlen($user->getEmail())) {
        $errors[] = 'You must enter an e-mail address.';
        $e_email = 'Required';
      }

      if (!$errors) {
        $user->save();
        return id(new AphrontRedirectResponse())
          ->setURI('/settings/page/email/?saved=true');
      }
    }

    $notice = null;
    if (!$errors) {
      if ($request->getStr('saved')) {
        $notice = new AphrontErrorView();
        $notice->setSeverity(AphrontErrorView::SEVERITY_NOTICE);
        $notice->setTitle('Changes Saved');
        $notice->appendChild('<p>Your changes have been saved.</p>');
      }
    } else {
      $notice = new AphrontErrorView();
      $notice->setTitle('Form Errors');
      $notice->setErrors($errors);
    }

    $form = new AphrontFormView();
    $form
      ->setUser($user)
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('Email')
          ->setName('email')
          ->setDisabled(!$editable)
          ->setCaption(
            'Note: there is no email validation yet; double-check your '.
            'typing.')
          ->setValue($user->getEmail())
          ->setError($e_email));

    if ($editable) {
      $form
        ->appendChild(
          id(new AphrontFormSubmitControl())
            ->setValue('Save'));
    }

    $panel = new AphrontPanelView();
    $panel->setHeader('Email Settings');
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
