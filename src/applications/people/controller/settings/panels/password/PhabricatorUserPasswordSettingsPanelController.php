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

class PhabricatorUserPasswordSettingsPanelController
  extends PhabricatorUserSettingsPanelController {

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();
    $editable = $this->getAccountEditable();

    // There's no sense in showing a change password panel if the user
    // can't change their password
    if (!$editable ||
        !PhabricatorEnv::getEnvConfig('auth.password-auth-enabled')) {
      return new Aphront400Response();
    }

    $errors = array();
    if ($request->isFormPost()) {
      if ($user->comparePassword($request->getStr('old_pw'))) {
        $pass = $request->getStr('new_pw');
        $conf = $request->getStr('conf_pw');
        if ($pass === $conf) {
          if (strlen($pass)) {
            $user->setPassword($pass);
            // This write is unguarded because the CSRF token has already
            // been checked in the call to $request->isFormPost() and
            // the CSRF token depends on the password hash, so when it
            // is changed here the CSRF token check will fail.
            $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
            $user->save();
            unset($unguarded);
            return id(new AphrontRedirectResponse())
              ->setURI('/settings/page/password/?saved=true');
          } else {
            $errors[] = 'Your new password is too short.';
          }
        } else {
          $errors[] = 'New password and confirmation do not match.';
        }
      } else {
        $errors[] = 'The old password you entered is incorrect.';
      }
    }

    $notice = null;
    if (!$errors) {
      if ($request->getStr('saved')) {
        $notice = new AphrontErrorView();
        $notice->setSeverity(AphrontErrorView::SEVERITY_NOTICE);
        $notice->setTitle('Changes Saved');
        $notice->appendChild('<p>Your password has been updated.</p>');
      }
    } else {
      $notice = new AphrontErrorView();
      $notice->setTitle('Error Changing Password');
      $notice->setErrors($errors);
    }

    $form = new AphrontFormView();
    $form
      ->setUser($user)
      ->appendChild(
        id(new AphrontFormPasswordControl())
          ->setLabel('Old Password')
          ->setName('old_pw'));
    $form
      ->appendChild(
        id(new AphrontFormPasswordControl())
          ->setLabel('New Password')
          ->setName('new_pw'));
    $form
      ->appendChild(
        id(new AphrontFormPasswordControl())
          ->setLabel('Confirm Password')
          ->setName('conf_pw'));
    $form
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue('Save'));

    $panel = new AphrontPanelView();
    $panel->setHeader('Change Password');
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
