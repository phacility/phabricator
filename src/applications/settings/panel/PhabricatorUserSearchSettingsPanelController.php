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

final class PhabricatorUserSearchSettingsPanelController
  extends PhabricatorUserSettingsPanelController {

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();
    $preferences = $user->loadPreferences();

    $pref_jump     = PhabricatorUserPreferences::PREFERENCE_SEARCHBAR_JUMP;
    $pref_shortcut = PhabricatorUserPreferences::PREFERENCE_SEARCH_SHORTCUT;

    if ($request->isFormPost()) {
      $preferences->setPreference($pref_jump,
        $request->getBool($pref_jump));

      $preferences->setPreference($pref_shortcut,
        $request->getBool($pref_shortcut));

      $preferences->save();
      return id(new AphrontRedirectResponse())
        ->setURI('/settings/page/search/?saved=true');
    }

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->setAction('/settings/page/search/')
      ->appendChild(
        id(new AphrontFormCheckboxControl())
          ->addCheckbox($pref_jump,
            1,
            'Enable jump nav functionality in all search boxes.',
            $preferences->getPreference($pref_jump, 1))
          ->addCheckbox($pref_shortcut,
            1,
            '\'/\' focuses search box.',
            $preferences->getPreference($pref_shortcut, 1))
            )
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue('Save'));

    $panel = new AphrontPanelView();
    $panel->setWidth(AphrontPanelView::WIDTH_FORM);
    $panel->setHeader('Search Preferences');
    $panel->appendChild($form);

    $error_view = null;
    if ($request->getStr('saved') === 'true') {
      $error_view = id(new AphrontErrorView())
        ->setTitle('Preferences Saved')
        ->setSeverity(AphrontErrorView::SEVERITY_NOTICE)
        ->setErrors(array('Your preferences have been saved.'));
    }

    return id(new AphrontNullView())
      ->appendChild(
        array(
          $error_view,
          $panel,
        ));
  }
}

