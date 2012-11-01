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
      foreach ($this->getMailTags() as $key => $label) {
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
        $notice->setTitle('Changes Saved');
        $notice->appendChild('<p>Your changes have been saved.</p>');
      }
    } else {
      $notice = new AphrontErrorView();
      $notice->setTitle('Form Errors');
      $notice->setErrors($errors);
    }

    $re_prefix_default = PhabricatorEnv::getEnvConfig('metamta.re-prefix')
      ? 'Enabled'
      : 'Disabled';

    $vary_default = PhabricatorEnv::getEnvConfig('metamta.vary-subjects')
      ? 'Vary'
      : 'Do Not Vary';

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
          ->setLabel('Self Actions')
          ->setName($pref_no_self_mail)
          ->setOptions(
            array(
              '0' => 'Send me an email when I take an action',
              '1' => 'Do not send me an email when I take an action',
            ))
          ->setCaption('You can disable email about your own actions.')
          ->setValue($preferences->getPreference($pref_no_self_mail, 0)));

    if (PhabricatorMetaMTAMail::shouldMultiplexAllMail()) {
      $re_control = id(new AphrontFormSelectControl())
        ->setName($pref_re_prefix)
        ->setOptions(
          array(
            'default'   => 'Use Server Default ('.$re_prefix_default.')',
            'true'      => 'Enable "Re:" prefix',
            'false'     => 'Disable "Re:" prefix',
          ))
        ->setValue($re_prefix_value);

      $vary_control = id(new AphrontFormSelectControl())
        ->setName($pref_vary)
        ->setOptions(
          array(
            'default'   => 'Use Server Default ('.$vary_default.')',
            'true'      => 'Vary Subjects',
            'false'     => 'Do Not Vary Subjects',
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
          ->setLabel('Add "Re:" Prefix')
          ->setCaption(
            'Enable this option to fix threading in Mail.app on OS X Lion, '.
            'or if you like "Re:" in your email subjects.'))
      ->appendChild(
        $vary_control
          ->setLabel('Vary Subjects')
          ->setCaption(
            'This option adds more information to email subjects, but may '.
            'break threading in some clients.'));

    $form
      ->appendChild(
        '<br />'.
        '<p class="aphront-form-instructions">'.
          'You can customize what mail you receive from Phabricator here.'.
        '</p>'.
        '<p class="aphront-form-instructions">'.
          '<strong>NOTE:</strong> If an update makes several changes (like '.
          'adding CCs to a task, closing it, and adding a comment) you will '.
          'still receive an email as long as at least one of the changes '.
          'is set to notify you.'.
        '</p>'
        );

    $mailtags = $preferences->getPreference('mailtags', array());

    $form
      ->appendChild(
        $this->buildMailTagCheckboxes(
          $this->getDifferentialMailTags(),
          $mailtags)
          ->setLabel('Differential'))
      ->appendChild(
        $this->buildMailTagCheckboxes(
          $this->getManiphestMailTags(),
          $mailtags)
          ->setLabel('Maniphest'));

    $form
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue('Save Preferences'));

    $panel = new AphrontPanelView();
    $panel->setHeader('Email Preferences');
    $panel->setWidth(AphrontPanelView::WIDTH_FORM);
    $panel->appendChild($form);

    return id(new AphrontNullView())
      ->appendChild(
        array(
          $notice,
          $panel,
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
