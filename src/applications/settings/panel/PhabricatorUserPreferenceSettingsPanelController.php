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

final class PhabricatorUserPreferenceSettingsPanelController
  extends PhabricatorUserSettingsPanelController {

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();
    $preferences = $user->loadPreferences();

    $pref_monospaced = PhabricatorUserPreferences::PREFERENCE_MONOSPACED;
    $pref_editor     = PhabricatorUserPreferences::PREFERENCE_EDITOR;
    $pref_titles     = PhabricatorUserPreferences::PREFERENCE_TITLES;
    $pref_symbols    = PhabricatorUserPreferences::PREFERENCE_DIFFUSION_SYMBOLS;

    if ($request->isFormPost()) {
      $monospaced = $request->getStr($pref_monospaced);

      // Prevent the user from doing stupid things.
      $monospaced = preg_replace('/[^a-z0-9 ,"]+/i', '', $monospaced);

      $preferences->setPreference($pref_titles, $request->getStr($pref_titles));
      $preferences->setPreference($pref_editor, $request->getStr($pref_editor));
      $preferences->setPreference($pref_symbols,
        $request->getStr($pref_symbols));
      $preferences->setPreference($pref_monospaced, $monospaced);

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

    $editor_doc_link = phutil_render_tag(
      'a',
      array(
        'href' => PhabricatorEnv::getDoclink(
          'article/User_Guide_Configuring_an_External_Editor.html'),
      ),
      'User Guide: Configuring an External Editor');

    $font_default = PhabricatorEnv::getEnvConfig('style.monospace');
    $font_default = phutil_escape_html($font_default);

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->setAction('/settings/page/preferences/')
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel('Page Titles')
          ->setName($pref_titles)
          ->setValue($preferences->getPreference($pref_titles))
          ->setOptions(
            array(
              'glyph' =>
              "In page titles, show Tool names as unicode glyphs: \xE2\x9A\x99",
              'text' =>
              'In page titles, show Tool names as plain text: [Differential]',
            )))
      ->appendChild(
        id(new AphrontFormTextControl())
        ->setLabel('Editor Link')
        ->setName($pref_editor)
        ->setCaption(
          'Link to edit files in external editor. '.
          '%f is replaced by filename, %l by line number, %r by repository '.
          'callsign, %% by literal %. '.
          "For documentation, see {$editor_doc_link}.")
        ->setValue($preferences->getPreference($pref_editor)))
      ->appendChild(
        id(new AphrontFormTextControl())
        ->setLabel('Monospaced Font')
        ->setName($pref_monospaced)
        ->setCaption(
          'Overrides default fonts in tools like Differential. '.
          '(Default: '.$font_default.')')
        ->setValue($preferences->getPreference($pref_monospaced)))
      ->appendChild(
        id(new AphrontFormMarkupControl())
        ->setValue(
          '<pre class="PhabricatorMonospaced">'.
          phutil_escape_html($example_string).
          '</pre>'))
      ->appendChild(
        id(new AphrontFormRadioButtonControl())
        ->setLabel('Symbol Links')
        ->setName($pref_symbols)
        ->setValue($preferences->getPreference($pref_symbols) ?: 'enabled')
        ->addButton('enabled', 'Enabled (default)',
          'Use this setting to disable linking symbol names in Differential '.
          'and Diffusion to their definitions. This is enabled by default.')
        ->addButton('disabled', 'Disabled', null))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue('Save Preferences'));

    $panel = new AphrontPanelView();
    $panel->setWidth(AphrontPanelView::WIDTH_WIDE);
    $panel->setHeader('Display Preferences');
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

