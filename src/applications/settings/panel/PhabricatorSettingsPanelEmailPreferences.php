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

    $pref_re_prefix = PhabricatorUserPreferences::PREFERENCE_RE_PREFIX;
    $pref_vary = PhabricatorUserPreferences::PREFERENCE_VARY_SUBJECT;
    $pref_no_self_mail = PhabricatorUserPreferences::PREFERENCE_NO_SELF_MAIL;

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

      $preferences->setPreference(
        $pref_no_self_mail,
        $request->getStr($pref_no_self_mail));

      $new_tags = $request->getArr('mailtags');
      $mailtags = $preferences->getPreference('mailtags', array());
      $all_tags = $this->getMailTags();

      $maniphest = 'PhabricatorApplicationManiphest';
      if (!PhabricatorApplication::isClassInstalled($maniphest)) {
        $all_tags = array_diff_key($all_tags, $this->getManiphestMailTags());
      }

      foreach ($all_tags as $key => $label) {
        $mailtags[$key] = (bool)idx($new_tags, $key, false);
      }
      $preferences->setPreference('mailtags', $mailtags);

      $preferences->save();

      return id(new AphrontRedirectResponse())
        ->setURI($this->getPanelURI('?saved=true'));
    }

    $notice = null;
    if (!$errors) {
      if ($request->getStr('saved')) {
        $notice = new AphrontErrorView();
        $notice->setSeverity(AphrontErrorView::SEVERITY_NOTICE);
        $notice->setTitle(pht('Changes Saved'));
        $notice->appendChild(
          phutil_tag('p', array(), pht('Your changes have been saved.')));
      }
    } else {
      $notice = new AphrontErrorView();
      $notice->setTitle(pht('Form Errors'));
      $notice->setErrors($errors);
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
      ->setUser($user)
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel(pht('Self Actions'))
          ->setName($pref_no_self_mail)
          ->setOptions(
            array(
              '0' => pht('Send me an email when I take an action'),
              '1' => pht('Do not send me an email when I take an action'),
            ))
          ->setCaption(pht('You can disable email about your own actions.'))
          ->setValue($preferences->getPreference($pref_no_self_mail, 0)));

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
      ->appendChild(
        $re_control
          ->setLabel(pht('Add "Re:" Prefix'))
          ->setCaption(
            pht('Enable this option to fix threading in Mail.app on OS X Lion,'.
            ' or if you like "Re:" in your email subjects.')))
      ->appendChild(
        $vary_control
          ->setLabel(pht('Vary Subjects'))
          ->setCaption(
            pht('This option adds more information to email subjects, but may '.
            'break threading in some clients.')));

    $form
      ->appendChild(hsprintf(
        '<br />'.
        '<p class="aphront-form-instructions">%s</p>'.
        '<p class="aphront-form-instructions">'.
          '<strong>%s</strong> %s</p>',
        pht('You can customize what mail you receive from Phabricator here.'),
        pht('NOTE:'),
        pht('If an update makes several changes (like '.
          'adding CCs to a task, closing it, and adding a comment) you will '.
          'still receive an email as long as at least one of the changes '.
          'is set to notify you.')));

    $mailtags = $preferences->getPreference('mailtags', array());

    $form
      ->appendChild(
        $this->buildMailTagCheckboxes(
          $this->getDifferentialMailTags(),
          $mailtags)
          ->setLabel(pht('Differential')));

    $maniphest = 'PhabricatorApplicationManiphest';
    if (PhabricatorApplication::isClassInstalled($maniphest)) {
      $form->appendChild(
        $this->buildMailTagCheckboxes(
          $this->getManiphestMailTags(),
          $mailtags)
          ->setLabel(pht('Maniphest')));
    }

    $form
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Save Preferences')));

    $header = new PhabricatorHeaderView();
    $header->setHeader(pht('Email Preferences'));

    return id(new AphrontNullView())
      ->appendChild(
        array(
          $notice,
          $header,
          $form,
        ));
  }

  private function getMailTags() {
    return array(
      MetaMTANotificationType::TYPE_DIFFERENTIAL_REVIEWERS =>
        pht("Send me email when a revision's reviewers change."),
      MetaMTANotificationType::TYPE_DIFFERENTIAL_CLOSED =>
        pht("Send me email when a revision is closed."),
      MetaMTANotificationType::TYPE_DIFFERENTIAL_CC =>
        pht("Send me email when a revision's CCs change."),
      MetaMTANotificationType::TYPE_DIFFERENTIAL_COMMENT =>
        pht("Send me email when a revision is commented on."),
      MetaMTANotificationType::TYPE_DIFFERENTIAL_UPDATED =>
        pht("Send me email when a revision is updated."),
      MetaMTANotificationType::TYPE_DIFFERENTIAL_REVIEW_REQUEST =>
        pht("Send me email when I am requested to review a revision."),
      MetaMTANotificationType::TYPE_DIFFERENTIAL_OTHER =>
        pht("Send me email for any other activity not listed above."),

      MetaMTANotificationType::TYPE_MANIPHEST_STATUS =>
        pht("Send me email when a task's status changes."),
      MetaMTANotificationType::TYPE_MANIPHEST_OWNER =>
        pht("Send me email when a task's owner changes."),
      MetaMTANotificationType::TYPE_MANIPHEST_PRIORITY =>
        pht("Send me email when a task's priority changes."),
      MetaMTANotificationType::TYPE_MANIPHEST_CC =>
        pht("Send me email when a task's CCs change."),
      MetaMTANotificationType::TYPE_MANIPHEST_PROJECTS =>
        pht("Send me email when a task's associated projects change."),
      MetaMTANotificationType::TYPE_MANIPHEST_COMMENT =>
        pht("Send me email when a task is commented on."),
      MetaMTANotificationType::TYPE_MANIPHEST_OTHER =>
        pht("Send me email for any other activity not listed above."),

    );
  }

  private function getManiphestMailTags() {
    return array_select_keys(
      $this->getMailTags(),
      array(
        MetaMTANotificationType::TYPE_MANIPHEST_STATUS,
        MetaMTANotificationType::TYPE_MANIPHEST_OWNER,
        MetaMTANotificationType::TYPE_MANIPHEST_PRIORITY,
        MetaMTANotificationType::TYPE_MANIPHEST_CC,
        MetaMTANotificationType::TYPE_MANIPHEST_PROJECTS,
        MetaMTANotificationType::TYPE_MANIPHEST_COMMENT,
        MetaMTANotificationType::TYPE_MANIPHEST_OTHER,
      ));
  }

  private function getDifferentialMailTags() {
    return array_select_keys(
      $this->getMailTags(),
      array(
        MetaMTANotificationType::TYPE_DIFFERENTIAL_REVIEWERS,
        MetaMTANotificationType::TYPE_DIFFERENTIAL_CLOSED,
        MetaMTANotificationType::TYPE_DIFFERENTIAL_CC,
        MetaMTANotificationType::TYPE_DIFFERENTIAL_COMMENT,
        MetaMTANotificationType::TYPE_DIFFERENTIAL_UPDATED,
        MetaMTANotificationType::TYPE_DIFFERENTIAL_REVIEW_REQUEST,
        MetaMTANotificationType::TYPE_DIFFERENTIAL_OTHER,
      ));
  }

  private function buildMailTagCheckboxes(
    array $tags,
    array $prefs) {

    $control = new AphrontFormCheckboxControl();
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
