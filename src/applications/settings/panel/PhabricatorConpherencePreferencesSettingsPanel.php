<?php

final class PhabricatorConpherencePreferencesSettingsPanel
  extends PhabricatorSettingsPanel {

  public function isEnabled() {
    return PhabricatorApplication::isClassInstalled(
      'PhabricatorConpherenceApplication');
  }

  public function getPanelKey() {
    return 'conpherence';
  }

  public function getPanelName() {
    return pht('Conpherence Preferences');
  }

  public function getPanelGroup() {
    return pht('Application Settings');
  }

  public function processRequest(AphrontRequest $request) {
    $user = $request->getUser();
    $preferences = $user->loadPreferences();

    $pref = PhabricatorUserPreferences::PREFERENCE_CONPH_NOTIFICATIONS;

    if ($request->isFormPost()) {
      $notifications = $request->getInt($pref);
      $preferences->setPreference($pref, $notifications);
      $preferences->save();
      return id(new AphrontRedirectResponse())
        ->setURI($this->getPanelURI('?saved=true'));
    }

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel(pht('Conpherence Notifications'))
          ->setName($pref)
          ->setValue($preferences->getPreference($pref))
          ->setOptions(
            array(
              ConpherenceSettings::EMAIL_ALWAYS
                => pht('Email Always'),
              ConpherenceSettings::NOTIFICATIONS_ONLY
                => pht('Notifications Only'),
            ))
          ->setCaption(
            pht(
              'Should Conpherence send emails for updates or '.
              'notifications only? This global setting can be overridden '.
              'on a per-thread basis within Conpherence.')))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Save Preferences')));

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Conpherence Preferences'))
      ->setForm($form)
      ->setFormSaved($request->getBool('saved'));

    return array(
      $form_box,
    );
  }

}
