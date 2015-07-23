<?php

final class PhabricatorDisplayPreferencesSettingsPanel
  extends PhabricatorSettingsPanel {

  public function getPanelKey() {
    return 'display';
  }

  public function getPanelName() {
    return pht('Display Preferences');
  }

  public function getPanelGroup() {
    return pht('Application Settings');
  }

  public function processRequest(AphrontRequest $request) {
    $user = $request->getUser();
    $preferences = $user->loadPreferences();

    $pref_monospaced   = PhabricatorUserPreferences::PREFERENCE_MONOSPACED;
    $pref_editor       = PhabricatorUserPreferences::PREFERENCE_EDITOR;
    $pref_multiedit    = PhabricatorUserPreferences::PREFERENCE_MULTIEDIT;
    $pref_titles       = PhabricatorUserPreferences::PREFERENCE_TITLES;
    $pref_monospaced_textareas =
      PhabricatorUserPreferences::PREFERENCE_MONOSPACED_TEXTAREAS;
    $pref_postprocessor =
      PhabricatorUserPreferences::PREFERENCE_RESOURCE_POSTPROCESSOR;

    $errors = array();
    $e_editor = null;
    if ($request->isFormPost()) {
      $monospaced = $request->getStr($pref_monospaced);
      $monospaced = PhabricatorUserPreferences::filterMonospacedCSSRule(
        $monospaced);

      $preferences->setPreference($pref_titles, $request->getStr($pref_titles));
      $preferences->setPreference($pref_editor, $request->getStr($pref_editor));
      $preferences->setPreference(
        $pref_multiedit,
        $request->getStr($pref_multiedit));
      $preferences->setPreference($pref_monospaced, $monospaced);
      $preferences->setPreference(
        $pref_monospaced_textareas,
        $request->getStr($pref_monospaced_textareas));
      $preferences->setPreference(
        $pref_postprocessor,
        $request->getStr($pref_postprocessor));

      $editor_pattern = $preferences->getPreference($pref_editor);
      if (strlen($editor_pattern)) {
        $ok = PhabricatorHelpEditorProtocolController::hasAllowedProtocol(
          $editor_pattern);
        if (!$ok) {
          $allowed_key = 'uri.allowed-editor-protocols';
          $allowed_protocols = PhabricatorEnv::getEnvConfig($allowed_key);

          $proto_names = array();
          foreach (array_keys($allowed_protocols) as $protocol) {
            $proto_names[] = $protocol.'://';
          }

          $errors[] = pht(
            'Editor link has an invalid or missing protocol. You must '.
            'use a whitelisted editor protocol from this list: %s. To '.
            'add protocols, update %s.',
            implode(', ', $proto_names),
            phutil_tag('tt', array(), $allowed_key));

          $e_editor = pht('Invalid');
        }
      }

      if (!$errors) {
        $preferences->save();
        return id(new AphrontRedirectResponse())
          ->setURI($this->getPanelURI('?saved=true'));
      }
    }

    $example_string = <<<EXAMPLE
// This is what your monospaced font currently looks like.
function helloWorld() {
  alert("Hello world!");
}
EXAMPLE;

    $editor_doc_link = phutil_tag(
      'a',
      array(
        'href' => PhabricatorEnv::getDoclink(
          'User Guide: Configuring an External Editor'),
      ),
      pht('User Guide: Configuring an External Editor'));

    $pref_monospaced_textareas_value = $preferences
      ->getPreference($pref_monospaced_textareas);
    if (!$pref_monospaced_textareas_value) {
      $pref_monospaced_textareas_value = 'disabled';
    }

    $editor_instructions = pht(
      'Link to edit files in external editor. '.
      '%%f is replaced by filename, %%l by line number, %%r by repository '.
      'callsign, %%%% by literal %%. For documentation, see: %s',
      $editor_doc_link);

    $font_instructions = pht(
      'Overrides default fonts in tools like Differential. '.
      'Input should be valid CSS "font" declaration, such as '.
      '"13px Consolas"');

    $postprocessor_map = CelerityPostprocessor::getAllPostprocessors();
    $postprocessor_map = mpull($postprocessor_map, 'getPostprocessorName');
    asort($postprocessor_map);
    $postprocessor_order = array(
      CelerityDefaultPostprocessor::POSTPROCESSOR_KEY,
    );

    $postprocessor_map = array_select_keys(
      $postprocessor_map,
      $postprocessor_order) + $postprocessor_map;

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->appendControl(
        id(new AphrontFormSelectControl())
          ->setLabel(pht('Accessibility'))
          ->setName($pref_postprocessor)
          ->setValue($preferences->getPreference($pref_postprocessor))
          ->setOptions($postprocessor_map))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel(pht('Page Titles'))
          ->setName($pref_titles)
          ->setValue($preferences->getPreference($pref_titles))
          ->setOptions(
            array(
              'glyph' =>
              pht(
                'In page titles, show Tool names as unicode glyphs: %s',
                "\xE2\x9A\x99"),
              'text' =>
              pht(
                'In page titles, show Tool names as plain text: '.
                '[Differential]'),
            )))
      ->appendChild(
        id(new AphrontFormTextControl())
        ->setLabel(pht('Editor Link'))
        ->setName($pref_editor)
        ->setCaption($editor_instructions)
        ->setError($e_editor)
        ->setValue($preferences->getPreference($pref_editor)))
      ->appendChild(
        id(new AphrontFormSelectControl())
        ->setLabel(pht('Edit Multiple Files'))
        ->setName($pref_multiedit)
        ->setOptions(array(
          '' => pht('Supported (paths separated by spaces)'),
          'disable' => pht('Not Supported'),
        ))
        ->setValue($preferences->getPreference($pref_multiedit)))
      ->appendChild(
        id(new AphrontFormTextControl())
        ->setLabel(pht('Monospaced Font'))
        ->setName($pref_monospaced)
        ->setCaption($font_instructions)
        ->setValue($preferences->getPreference($pref_monospaced)))
      ->appendChild(
        id(new AphrontFormMarkupControl())
        ->setValue(phutil_tag(
          'pre',
          array('class' => 'PhabricatorMonospaced'),
          $example_string)))
      ->appendChild(
        id(new AphrontFormRadioButtonControl())
        ->setLabel(pht('Monospaced Textareas'))
        ->setName($pref_monospaced_textareas)
        ->setValue($pref_monospaced_textareas_value)
        ->addButton('enabled', pht('Enabled'),
          pht('Show all textareas using the monospaced font defined above.'))
        ->addButton('disabled', pht('Disabled'), null));

    $form->appendChild(
      id(new AphrontFormSubmitControl())
        ->setValue(pht('Save Preferences')));

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Display Preferences'))
      ->setFormErrors($errors)
      ->setFormSaved($request->getStr('saved') === 'true')
      ->setForm($form);

    return array(
      $form_box,
    );
  }
}
