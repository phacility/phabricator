<?php

final class PhabricatorEmailPreferencesSettingsPanel
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

  public function isEditableByAdministrators() {
    if ($this->getUser()->getIsMailingList()) {
      return true;
    }

    return false;
  }

  public function processRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $user = $this->getUser();

    $preferences = $user->loadPreferences();

    $pref_no_mail = PhabricatorUserPreferences::PREFERENCE_NO_MAIL;
    $pref_no_self_mail = PhabricatorUserPreferences::PREFERENCE_NO_SELF_MAIL;

    $value_email = PhabricatorUserPreferences::MAILTAG_PREFERENCE_EMAIL;

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
        $mailtags[$key] = (int)idx($new_tags, $key, $value_email);
      }
      $preferences->setPreference('mailtags', $mailtags);

      $preferences->save();

      return id(new AphrontRedirectResponse())
        ->setURI($this->getPanelURI('?saved=true'));
    }

    $form = new AphrontFormView();
    $form
      ->setUser($viewer)
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
        'You can adjust **Application Settings** here to customize when '.
        'you are emailed and notified.'.
        "\n\n".
        "| Setting | Effect\n".
        "| ------- | -------\n".
        "| Email | You will receive an email and a notification, but the ".
        "notification will be marked \"read\".\n".
        "| Notify | You will receive an unread notification only.\n".
        "| Ignore | You will receive nothing.\n".
        "\n\n".
        'If an update makes several changes (like adding CCs to a task, '.
        'closing it, and adding a comment) you will receive the strongest '.
        'notification any of the changes is configured to deliver.'.
        "\n\n".
        'These preferences **only** apply to objects you are connected to '.
        '(for example, Revisions where you are a reviewer or tasks you are '.
        'CC\'d on). To receive email alerts when other objects are created, '.
        'configure [[ /herald/ | Herald Rules ]].'));

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
        array_diff_key($editor->getMailTagsMap(), $common_tags),
      );
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

    $value_email = PhabricatorUserPreferences::MAILTAG_PREFERENCE_EMAIL;
    $value_notify = PhabricatorUserPreferences::MAILTAG_PREFERENCE_NOTIFY;
    $value_ignore = PhabricatorUserPreferences::MAILTAG_PREFERENCE_IGNORE;

    $content = array();
    foreach ($tags as $key => $label) {
      $select = AphrontFormSelectControl::renderSelectTag(
        (int)idx($prefs, $key, $value_email),
        array(
          $value_email => pht("\xE2\x9A\xAB Email"),
          $value_notify => pht("\xE2\x97\x90 Notify"),
          $value_ignore => pht("\xE2\x9A\xAA Ignore"),
        ),
        array(
          'name' => 'mailtags['.$key.']',
        ));

      $content[] = phutil_tag(
        'div',
        array(
          'class' => 'psb',
        ),
        array(
          $select,
          ' ',
          $label,
        ));
    }

    $control = new AphrontFormStaticControl();
    $control->setLabel($control_label);
    $control->setValue($content);

    return $control;
  }

}
