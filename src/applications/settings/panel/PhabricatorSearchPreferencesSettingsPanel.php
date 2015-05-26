<?php

final class PhabricatorSearchPreferencesSettingsPanel
  extends PhabricatorSettingsPanel {

  public function getPanelKey() {
    return 'search';
  }

  public function getPanelName() {
    return pht('Search Preferences');
  }

  public function getPanelGroup() {
    return pht('Application Settings');
  }

  public function processRequest(AphrontRequest $request) {
    $user = $request->getUser();
    $preferences = $user->loadPreferences();

    $pref_jump     = PhabricatorUserPreferences::PREFERENCE_SEARCHBAR_JUMP;
    $pref_shortcut = PhabricatorUserPreferences::PREFERENCE_SEARCH_SHORTCUT;

    if ($request->isFormPost()) {
      $preferences->setPreference($pref_jump,
        $request->getBool($pref_jump));

      $preferences->setPreference($pref_shortcut,
        $request->getBool($pref_shortcut));

      $preferences->save();
      return id(new AphrontRedirectResponse())
        ->setURI($this->getPanelURI('?saved=true'));
    }

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->appendChild(
        id(new AphrontFormCheckboxControl())
          ->addCheckbox($pref_jump,
            1,
            pht('Enable jump nav functionality in all search boxes.'),
            $preferences->getPreference($pref_jump, 1))
          ->addCheckbox($pref_shortcut,
            1,
            pht("Press '%s' to focus the search input.", '/'),
            $preferences->getPreference($pref_shortcut, 1)))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Save')));

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Search Preferences'))
      ->setFormSaved($request->getStr('saved') === 'true')
      ->setForm($form);

    return array(
      $form_box,
    );
  }
}
