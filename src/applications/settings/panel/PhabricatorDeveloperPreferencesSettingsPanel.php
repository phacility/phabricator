<?php

final class PhabricatorDeveloperPreferencesSettingsPanel
  extends PhabricatorSettingsPanel {

  public function getPanelKey() {
    return 'developer';
  }

  public function getPanelName() {
    return pht('Developer Settings');
  }

  public function getPanelGroup() {
    return pht('Developer');
  }

  public function processRequest(AphrontRequest $request) {
    $user = $request->getUser();
    $preferences = $user->loadPreferences();

    $pref_dark_console = PhabricatorUserPreferences::PREFERENCE_DARK_CONSOLE;

    $dark_console_value = $preferences->getPreference($pref_dark_console);

    if ($request->isFormPost()) {
      $new_dark_console = $request->getBool($pref_dark_console);
      $preferences->setPreference($pref_dark_console, $new_dark_console);

      // If the user turned Dark Console on, enable it (as though they had hit
      // "`").
      if ($new_dark_console && !$dark_console_value) {
        $user->setConsoleVisible(true);
        $user->save();
      }

      $preferences->save();

      return id(new AphrontRedirectResponse())
        ->setURI($this->getPanelURI('?saved=true'));
    }

    $is_console_enabled = PhabricatorEnv::getEnvConfig('darkconsole.enabled');

    $preamble = pht(
      "**DarkConsole** is a developer console which can help build and ".
      "debug Phabricator applications. It includes tools for understanding ".
      "errors, performance, service calls, and other low-level aspects of ".
      "Phabricator's inner workings.");

    if ($is_console_enabled) {
      $instructions = pht(
        "%s\n\n".
        'You can enable it for your account below. Enabling DarkConsole will '.
        'slightly decrease performance, but give you access to debugging '.
        'tools. You may want to disable it again later if you only need it '.
        'temporarily.'.
        "\n\n".
        'NOTE: After enabling DarkConsole, **press the ##%s## key on your '.
        'keyboard** to show or hide it.',
        $preamble,
        '`');
    } else {
      $instructions = pht(
        "%s\n\n".
        'Before you can turn on DarkConsole, it needs to be enabled in '.
        'the configuration for this install (`%s`).',
        $preamble,
        'darkconsole.enabled');
    }

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->appendRemarkupInstructions($instructions)
      ->appendChild(
        id(new AphrontFormSelectControl())
        ->setLabel(pht('Dark Console'))
        ->setName($pref_dark_console)
        ->setValue($dark_console_value)
        ->setOptions(
          array(
            0 => pht('Disable DarkConsole'),
            1 => pht('Enable DarkConsole'),
          ))
        ->setDisabled(!$is_console_enabled))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Save Preferences')));

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Developer Settings'))
      ->setFormSaved($request->getBool('saved'))
      ->setForm($form);

    return array(
      $form_box,
    );
  }
}
