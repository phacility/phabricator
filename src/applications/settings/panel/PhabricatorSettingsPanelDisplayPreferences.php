<?php

final class PhabricatorSettingsPanelDisplayPreferences
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
    $pref_dark_console = PhabricatorUserPreferences::PREFERENCE_DARK_CONSOLE;
    $pref_editor       = PhabricatorUserPreferences::PREFERENCE_EDITOR;
    $pref_multiedit    = PhabricatorUserPreferences::PREFERENCE_MULTIEDIT;
    $pref_titles       = PhabricatorUserPreferences::PREFERENCE_TITLES;
    $pref_monospaced_textareas =
      PhabricatorUserPreferences::PREFERENCE_MONOSPACED_TEXTAREAS;

    if ($request->isFormPost()) {
      $monospaced = $request->getStr($pref_monospaced);

      // Prevent the user from doing stupid things.
      $monospaced = preg_replace('/[^a-z0-9 ,"]+/i', '', $monospaced);

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
        $pref_dark_console,
        $request->getBool($pref_dark_console));

      $preferences->save();
      return id(new AphrontRedirectResponse())
        ->setURI($this->getPanelURI('?saved=true'));
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
          'article/User_Guide_Configuring_an_External_Editor.html'),
      ),
      'User Guide: Configuring an External Editor');

    $font_default = PhabricatorEnv::getEnvConfig('style.monospace');

    $pref_monospaced_textareas_value = $preferences
      ->getPreference($pref_monospaced_textareas);
    if (!$pref_monospaced_textareas_value) {
      $pref_monospaced_textareas_value = 'disabled';
    }
    $pref_dark_console_value = $preferences->getPreference($pref_dark_console);
    if (!$pref_dark_console_value) {
        $pref_dark_console_value = 0;
    }

    $form = id(new AphrontFormView())
      ->setUser($user)
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
        ->setCaption(hsprintf(
          'Link to edit files in external editor. '.
          '%%f is replaced by filename, %%l by line number, %%r by repository '.
          'callsign, %%%% by literal %%. For documentation, see %s.',
          $editor_doc_link))
        ->setValue($preferences->getPreference($pref_editor)))
      ->appendChild(
        id(new AphrontFormSelectControl())
        ->setLabel('Edit Multiple Files')
        ->setName($pref_multiedit)
        ->setOptions(array(
          '' => 'Supported (paths separated by spaces)',
          'disable' => 'Not Supported',
        ))
        ->setValue($preferences->getPreference($pref_multiedit)))
      ->appendChild(
        id(new AphrontFormTextControl())
        ->setLabel('Monospaced Font')
        ->setName($pref_monospaced)
        ->setCaption(hsprintf(
          'Overrides default fonts in tools like Differential.<br />'.
          '(Default: %s)',
          $font_default))
        ->setValue($preferences->getPreference($pref_monospaced)))
      ->appendChild(
        id(new AphrontFormMarkupControl())
        ->setValue(phutil_tag(
          'pre',
          array('class' => 'PhabricatorMonospaced'),
          $example_string)))
      ->appendChild(
        id(new AphrontFormRadioButtonControl())
        ->setLabel('Monospaced Textareas')
        ->setName($pref_monospaced_textareas)
        ->setValue($pref_monospaced_textareas_value)
        ->addButton('enabled', 'Enabled',
          'Show all textareas using the monospaced font defined above.')
        ->addButton('disabled', 'Disabled', null));

    if (PhabricatorEnv::getEnvConfig('darkconsole.enabled')) {
      $form->appendChild(
        id(new AphrontFormRadioButtonControl())
        ->setLabel('Dark Console')
        ->setName($pref_dark_console)
        ->setValue($pref_dark_console_value ?
            $pref_dark_console_value : 0)
        ->addButton(1, 'Enabled',
          'Enabling and using the built-in debugging console.')
        ->addButton(0, 'Disabled', null));
    }

    $form->appendChild(
      id(new AphrontFormSubmitControl())
        ->setValue('Save Preferences'));

    $panel = new AphrontPanelView();
    $panel->setHeader('Display Preferences');
    $panel->appendChild($form);
    $panel->setNoBackground();

    $error_view = null;
    if ($request->getStr('saved') === 'true') {
      $error_view = id(new AphrontErrorView())
        ->setTitle('Preferences Saved')
        ->setSeverity(AphrontErrorView::SEVERITY_NOTICE)
        ->setErrors(array('Your preferences have been saved.'));
    }

    return array(
      $error_view,
      $panel,
    );
  }
}

