<?php

final class PhabricatorSettingsPanelEmailPreferences
  extends PhabricatorSettingsPanel {

  public function getPanelKey() {
    return 'emailpreferences';
  }

  public function getPanelName() {
    return pht('Email Preferences');
  }

  public function getPanelGroup() {
    return pht('Email');
  }

  public function processRequest(AphrontRequest $request) {
    $user = $request->getUser();

    $preferences = $user->loadPreferences();

    $pref_no_mail = PhabricatorUserPreferences::PREFERENCE_NO_MAIL;
    $pref_no_self_mail = PhabricatorUserPreferences::PREFERENCE_NO_SELF_MAIL;

    $errors = array();
    if ($request->isFormPost()) {
      $preferences->setPreference(
        $pref_no_mail,
        $request->getStr($pref_no_mail));

      $preferences->setPreference(
        $pref_no_self_mail,
        $request->getStr($pref_no_self_mail));

      $new_tags = $request->getArr('mailtags');
      $mailtags = $preferences->getPreference('mailtags', array());
      $all_tags = $this->getAllTags($user);

      foreach ($all_tags as $key => $label) {
        $mailtags[$key] = (bool)idx($new_tags, $key, false);
      }
      $preferences->setPreference('mailtags', $mailtags);

      $preferences->save();

      return id(new AphrontRedirectResponse())
        ->setURI($this->getPanelURI('?saved=true'));
    }

    $form = new AphrontFormView();
    $form
      ->setUser($user)
      ->appendRemarkupInstructions(
        pht(
          'These settings let you control how Phabricator notifies you about '.
          'events. You can configure Phabricator to send you an email, '.
          'just send a web notification, or not notify you at all.'))
      ->appendRemarkupInstructions(
        pht(
          'If you disable **Email Notifications**, Phabricator will never '.
          'send email to notify you about events. This preference overrides '.
          'all your other settings.'.
          "\n\n".
          "//You may still receive some administrative email, like password ".
          "reset email.//"))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel(pht('Email Notifications'))
          ->setName($pref_no_mail)
          ->setOptions(
            array(
              '0' => pht('Send me email notifications'),
              '1' => pht('Never send email notifications'),
            ))
          ->setValue($preferences->getPreference($pref_no_mail, 0)))
      ->appendRemarkupInstructions(
        pht(
          'If you disable **Self Actions**, Phabricator will not notify '.
          'you about actions you take.'))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel(pht('Self Actions'))
          ->setName($pref_no_self_mail)
          ->setOptions(
            array(
              '0' => pht('Send me an email when I take an action'),
              '1' => pht('Do not send me an email when I take an action'),
            ))
          ->setValue($preferences->getPreference($pref_no_self_mail, 0)));

    $mailtags = $preferences->getPreference('mailtags', array());

    $form->appendChild(
      id(new PHUIFormDividerControl()));

    $form->appendRemarkupInstructions(
      pht(
        'You can customize which kinds of events you receive email for '.
        'here. If you turn off email for a certain type of event, you '.
        'will receive an unread notification in Phabricator instead.'.
        "\n\n".
        'Phabricator notifications (shown in the menu bar) which you receive '.
        'an email for are marked read by default in Phabricator. If you turn '.
        'off email for a certain type of event, the corresponding '.
        'notification will not be marked read.'.
        "\n\n".
        'Note that if an update makes several changes (like adding CCs to a '.
        'task, closing it, and adding a comment) you will still receive '.
        'an email as long as at least one of the changes is set to notify '.
        'you.'.
        "\n\n".
        'These preferences **only** apply to objects you are connected to '.
        '(for example, Revisions where you are a reviewer or tasks you are '.
        'CC\'d on). To receive email alerts when other objects are created, '.
        'configure [[ /herald/ | Herald Rules ]].'.
        "\n\n".
        'Phabricator will send an email to your primary account when:'));

    $editors = $this->getAllEditorsWithTags($user);

    // Find all the tags shared by more than one application, and put them
    // in a "common" group.
    $all_tags = array();
    foreach ($editors as $editor) {
      foreach ($editor->getMailTagsMap() as $tag => $name) {
        if (empty($all_tags[$tag])) {
          $all_tags[$tag] = array(
            'count' => 0,
            'name' => $name,
          );
        }
        $all_tags[$tag]['count'];
      }
    }

    $common_tags = array();
    foreach ($all_tags as $tag => $info) {
      if ($info['count'] > 1) {
        $common_tags[$tag] = $info['name'];
      }
    }

    // Build up the groups of application-specific options.
    $tag_groups = array();
    foreach ($editors as $editor) {
      $tag_groups[] = array(
        $editor->getEditorObjectsDescription(),
        array_diff_key($editor->getMailTagsMap(), $common_tags));
    }

    // Sort them, then put "Common" at the top.
    $tag_groups = isort($tag_groups, 0);
    if ($common_tags) {
      array_unshift($tag_groups, array(pht('Common'), $common_tags));
    }

    // Finally, build the controls.
    foreach ($tag_groups as $spec) {
      list($label, $map) = $spec;
      $control = $this->buildMailTagControl($label, $map, $mailtags);
      $form->appendChild($control);
    }

    $form
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Save Preferences')));

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Email Preferences'))
      ->setFormSaved($request->getStr('saved'))
      ->setFormErrors($errors)
      ->setForm($form);

    return id(new AphrontNullView())
      ->appendChild(
        array(
          $form_box,
        ));
  }

  private function getAllEditorsWithTags(PhabricatorUser $user) {
    $editors = id(new PhutilSymbolLoader())
      ->setAncestorClass('PhabricatorApplicationTransactionEditor')
      ->loadObjects();

    foreach ($editors as $key => $editor) {
      // Remove editors which do not support mail tags.
      if (!$editor->getMailTagsMap()) {
        unset($editors[$key]);
      }

      // Remove editors for applications which are not installed.
      $app = $editor->getEditorApplicationClass();
      if ($app !== null) {
        if (!PhabricatorApplication::isClassInstalledForViewer($app, $user)) {
          unset($editors[$key]);
        }
      }
    }

    return $editors;
  }

  private function getAllTags(PhabricatorUser $user) {
    $tags = array();
    foreach ($this->getAllEditorsWithTags($user) as $editor) {
      $tags += $editor->getMailTagsMap();
    }
    return $tags;
  }

  private function buildMailTagControl(
    $control_label,
    array $tags,
    array $prefs) {

    $control = new AphrontFormCheckboxControl();
    $control->setLabel($control_label);
    foreach ($tags as $key => $label) {
      $control->addCheckbox(
        'mailtags['.$key.']',
        1,
        $label,
        idx($prefs, $key, 1));
    }

    return $control;
  }

}
