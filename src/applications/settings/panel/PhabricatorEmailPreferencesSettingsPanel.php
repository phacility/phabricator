<?php

final class PhabricatorEmailPreferencesSettingsPanel
  extends PhabricatorSettingsPanel {

  public function getPanelKey() {
    return 'emailpreferences';
  }

  public function getPanelName() {
    return pht('Email Preferences');
  }

  public function getPanelGroupKey() {
    return PhabricatorSettingsEmailPanelGroup::PANELGROUPKEY;
  }

  public function isManagementPanel() {
    if ($this->getUser()->getIsMailingList()) {
      return true;
    }

    return false;
  }

  public function isTemplatePanel() {
    return true;
  }

  public function processRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $user = $this->getUser();

    $preferences = $this->getPreferences();

    $value_email = PhabricatorEmailTagsSetting::VALUE_EMAIL;

    $errors = array();
    if ($request->isFormPost()) {
      $new_tags = $request->getArr('mailtags');
      $mailtags = $preferences->getPreference('mailtags', array());
      $all_tags = $this->getAllTags($user);

      foreach ($all_tags as $key => $label) {
        $mailtags[$key] = (int)idx($new_tags, $key, $value_email);
      }

      $this->writeSetting(
        $preferences,
        PhabricatorEmailTagsSetting::SETTINGKEY,
        $mailtags);

      return id(new AphrontRedirectResponse())
        ->setURI($this->getPanelURI('?saved=true'));
    }

    $mailtags = $preferences->getSettingValue(
      PhabricatorEmailTagsSetting::SETTINGKEY);

    $form = id(new AphrontFormView())
      ->setUser($viewer);

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

    return $form_box;
  }

  private function getAllEditorsWithTags(PhabricatorUser $user = null) {
    $editors = id(new PhutilClassMapQuery())
      ->setAncestorClass('PhabricatorApplicationTransactionEditor')
      ->setFilterMethod('getMailTagsMap')
      ->execute();

    foreach ($editors as $key => $editor) {
      // Remove editors for applications which are not installed.
      $app = $editor->getEditorApplicationClass();
      if ($app !== null && $user !== null) {
        if (!PhabricatorApplication::isClassInstalledForViewer($app, $user)) {
          unset($editors[$key]);
        }
      }
    }

    return $editors;
  }

  private function getAllTags(PhabricatorUser $user = null) {
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

    $value_email = PhabricatorEmailTagsSetting::VALUE_EMAIL;
    $value_notify = PhabricatorEmailTagsSetting::VALUE_NOTIFY;
    $value_ignore = PhabricatorEmailTagsSetting::VALUE_IGNORE;

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
