<?php

final class PhabricatorSettingsPanelEmailFormat
  extends PhabricatorSettingsPanel {

  public function getPanelKey() {
    return 'emailformat';
  }

  public function getPanelName() {
    return pht('Email Format');
  }

  public function getPanelGroup() {
    return pht('Email');
  }

  public function processRequest(AphrontRequest $request) {
    $user = $request->getUser();

    $preferences = $user->loadPreferences();

    $pref_re_prefix = PhabricatorUserPreferences::PREFERENCE_RE_PREFIX;
    $pref_vary = PhabricatorUserPreferences::PREFERENCE_VARY_SUBJECT;

    $errors = array();
    if ($request->isFormPost()) {

      if (PhabricatorMetaMTAMail::shouldMultiplexAllMail()) {
        if ($request->getStr($pref_re_prefix) == 'default') {
          $preferences->unsetPreference($pref_re_prefix);
        } else {
          $preferences->setPreference(
            $pref_re_prefix,
            $request->getBool($pref_re_prefix));
        }

        if ($request->getStr($pref_vary) == 'default') {
          $preferences->unsetPreference($pref_vary);
        } else {
          $preferences->setPreference(
            $pref_vary,
            $request->getBool($pref_vary));
        }
      }

      $preferences->save();

      return id(new AphrontRedirectResponse())
        ->setURI($this->getPanelURI('?saved=true'));
    }

    $re_prefix_default = PhabricatorEnv::getEnvConfig('metamta.re-prefix')
      ? pht('Enabled')
      : pht('Disabled');

    $vary_default = PhabricatorEnv::getEnvConfig('metamta.vary-subjects')
      ? pht('Vary')
      : pht('Do Not Vary');

    $re_prefix_value = $preferences->getPreference($pref_re_prefix);
    if ($re_prefix_value === null) {
      $re_prefix_value = 'default';
    } else {
      $re_prefix_value = $re_prefix_value
        ? 'true'
        : 'false';
    }

    $vary_value = $preferences->getPreference($pref_vary);
    if ($vary_value === null) {
      $vary_value = 'default';
    } else {
      $vary_value = $vary_value
        ? 'true'
        : 'false';
    }

    $form = new AphrontFormView();
    $form
      ->setUser($user);

    if (PhabricatorMetaMTAMail::shouldMultiplexAllMail()) {
      $re_control = id(new AphrontFormSelectControl())
        ->setName($pref_re_prefix)
        ->setOptions(
          array(
            'default'   => pht('Use Server Default (%s)', $re_prefix_default),
            'true'      => pht('Enable "Re:" prefix'),
            'false'     => pht('Disable "Re:" prefix'),
          ))
        ->setValue($re_prefix_value);

      $vary_control = id(new AphrontFormSelectControl())
        ->setName($pref_vary)
        ->setOptions(
          array(
            'default'   => pht('Use Server Default (%s)', $vary_default),
            'true'      => pht('Vary Subjects'),
            'false'     => pht('Do Not Vary Subjects'),
          ))
        ->setValue($vary_value);
    } else {
      $re_control = id(new AphrontFormStaticControl())
        ->setValue('Server Default ('.$re_prefix_default.')');

      $vary_control = id(new AphrontFormStaticControl())
        ->setValue('Server Default ('.$vary_default.')');
    }

    $form
      ->appendRemarkupInstructions(
        pht(
          'These settings fine-tune some technical aspects of how email is '.
          'formatted. You may be able to adjust them to make mail more '.
          'useful or improve threading.'));

    if (!PhabricatorMetaMTAMail::shouldMultiplexAllMail()) {
      $form->appendRemarkupInstructions(
        pht(
          'NOTE: This install of Phabricator is configured to send a '.
          'single mail message to all recipients, so all settings are '.
          'locked at the server default value.'));
    }

    $form
      ->appendRemarkupInstructions('')
      ->appendRemarkupInstructions(
        pht(
          'The **Add "Re:" Prefix** setting adds "Re:" in front of all '.
          'messages, even if they are not replies. If you use **Mail.app** on '.
          'Mac OS X, this may improve mail threading.'.
          "\n\n".
          "| Setting                | Example Mail Subject\n".
          "|------------------------|----------------\n".
          "| Enable \"Re:\" Prefix  | ".
          "`Re: [Differential] [Accepted] D123: Example Revision`\n".
          "| Disable \"Re:\" Prefix | ".
          "`[Differential] [Accepted] D123: Example Revision`"))
      ->appendChild(
        $re_control
          ->setLabel(pht('Add "Re:" Prefix')))
      ->appendRemarkupInstructions('')
      ->appendRemarkupInstructions(
        pht(
          'With **Vary Subjects** enabled, most mail subject lines will '.
          'include a brief description of their content, like **[Closed]** '.
          'for a notification about someone closing a task.'.
          "\n\n".
          "| Setting              | Example Mail Subject\n".
          "|----------------------|----------------\n".
          "| Vary Subjects        | ".
          "`[Maniphest] [Closed] T123: Example Task`\n".
          "| Do Not Vary Subjects | ".
          "`[Maniphest] T123: Example Task`\n".
          "\n".
          'This can make mail more useful, but some clients have difficulty '.
          'threading these messages. Disabling this option may improve '.
          'threading, at the cost of less useful subject lines.'))
      ->appendChild(
        $vary_control
          ->setLabel(pht('Vary Subjects')));

    $form
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Save Preferences')));

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Email Format'))
      ->setFormSaved($request->getStr('saved'))
      ->setFormErrors($errors)
      ->setForm($form);

    return id(new AphrontNullView())
      ->appendChild(
        array(
          $form_box,
        ));
  }

}
