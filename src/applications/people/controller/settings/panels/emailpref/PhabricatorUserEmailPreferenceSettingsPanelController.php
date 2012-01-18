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

class PhabricatorUserEmailPreferenceSettingsPanelController
  extends PhabricatorUserSettingsPanelController {

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $preferences = $user->loadPreferences();

    $pref_re_prefix = PhabricatorUserPreferences::PREFERENCE_RE_PREFIX;
    $pref_no_self_mail = PhabricatorUserPreferences::PREFERENCE_NO_SELF_MAIL;

    $errors = array();
    if ($request->isFormPost()) {

      if ($request->getStr($pref_re_prefix) == 'default') {
        $preferences->unsetPreference($pref_re_prefix);
      } else {
        $preferences->setPreference(
          $pref_re_prefix,
          $request->getBool($pref_re_prefix));
      }

      $preferences->setPreference(
        $pref_no_self_mail,
        $request->getStr($pref_no_self_mail));

      $preferences->save();

      return id(new AphrontRedirectResponse())
        ->setURI('/settings/page/emailpref/?saved=true');
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

    $re_prefix_default = PhabricatorEnv::getEnvConfig('metamta.re-prefix')
      ? 'Enabled'
      : 'Disabled';

    $re_prefix_value = $preferences->getPreference($pref_re_prefix);
    if ($re_prefix_value === null) {
      $re_prefix_value = 'defualt';
    } else {
      $re_prefix_value = $re_prefix_value
        ? 'true'
        : 'false';
    }

    $form = new AphrontFormView();
    $form
      ->setUser($user)
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel('Self Actions')
          ->setName($pref_no_self_mail)
          ->setOptions(
            array(
              '0' => 'Send me an email when I take an action',
              '1' => 'Do not send me an email when I take an action',
            ))
          ->setCaption('You can disable email about your own actions.')
          ->setValue($preferences->getPreference($pref_no_self_mail, 0)))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel('Add "Re:" Prefix')
          ->setName($pref_re_prefix)
          ->setCaption(
            'Enable this option to fix threading in Mail.app on OS X Lion, '.
            'or if you like "Re:" in your email subjects.')
          ->setOptions(
            array(
              'default'   => 'Use Server Default ('.$re_prefix_default.')',
              'true'      => 'Enable "Re:" prefix',
              'false'     => 'Disable "Re:" prefix',
            ))
          ->setValue($re_prefix_value));

    $form
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue('Save'));

    $panel = new AphrontPanelView();
    $panel->setHeader('Email Preferences');
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
