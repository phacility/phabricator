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
class PhabricatorUserPreferenceSettingsPanelController
  extends PhabricatorUserSettingsPanelController {

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();
    $preferences = $user->loadPreferences();

    if ($request->isFormPost()) {
      $monospaced = $request->getStr(
        PhabricatorUserPreferences::PREFERENCE_MONOSPACED);

      // Prevent the user from doing stupid things.
      $monospaced = preg_replace('/[^a-z0-9 ,"]+/i', '', $monospaced);

      $pref_dict = array(
        PhabricatorUserPreferences::PREFERENCE_TITLES =>
        $request->getStr(PhabricatorUserPreferences::PREFERENCE_TITLES),
        PhabricatorUserPreferences::PREFERENCE_MONOSPACED =>
        $monospaced);

      $preferences->setPreferences($pref_dict);
      $preferences->save();
      return id(new AphrontRedirectResponse())
        ->setURI('/settings/page/preferences/?saved=true');
    }

    $example_string = <<<EXAMPLE
// This is what your monospaced font currently looks like.
function helloWorld() {
  alert("Hello world!");
}
EXAMPLE;

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->setAction('/settings/page/preferences/')
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel('Page Titles')
          ->setName(PhabricatorUserPreferences::PREFERENCE_TITLES)
          ->setValue($preferences->getPreference(
                       PhabricatorUserPreferences::PREFERENCE_TITLES))
          ->setOptions(
            array(
              'glyph' =>
              "In page titles, show Tool names as unicode glyphs: \xE2\x9A\x99",
              'text' =>
              'In page titles, show Tool names as plain text: [Differential]',
            )))
      ->appendChild(
        id(new AphrontFormTextControl())
        ->setLabel('Monospaced Font')
        ->setName(PhabricatorUserPreferences::PREFERENCE_MONOSPACED)
        ->setCaption(
          'Overrides default fonts in tools like Differential. '.
          '(Default: 10px "Menlo", "Consolas", "Monaco", '.
          'monospace)')
        ->setValue($preferences->getPreference(
                     PhabricatorUserPreferences::PREFERENCE_MONOSPACED)))
      ->appendChild(
        id(new AphrontFormMarkupControl())
        ->setValue(
          '<pre class="PhabricatorMonospaced">'.
          phutil_escape_html($example_string).
          '</pre>'))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue('Save Preferences'));

    $panel = new AphrontPanelView();
    $panel->setWidth(AphrontPanelView::WIDTH_WIDE);
    $panel->setHeader('Phabricator Preferences');
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

