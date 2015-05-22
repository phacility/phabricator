<?php

final class PhabricatorDiffPreferencesSettingsPanel
  extends PhabricatorSettingsPanel {

  public function getPanelKey() {
    return 'diff';
  }

  public function getPanelName() {
    return pht('Diff Preferences');
  }

  public function getPanelGroup() {
    return pht('Application Settings');
  }

  public function processRequest(AphrontRequest $request) {
    $user = $request->getUser();
    $preferences = $user->loadPreferences();

    $pref_unified = PhabricatorUserPreferences::PREFERENCE_DIFF_UNIFIED;
    $pref_ghosts = PhabricatorUserPreferences::PREFERENCE_DIFF_GHOSTS;
    $pref_filetree = PhabricatorUserPreferences::PREFERENCE_DIFF_FILETREE;

    if ($request->isFormPost()) {
      $filetree = $request->getInt($pref_filetree);

      if ($filetree && !$preferences->getPreference($pref_filetree)) {
        $preferences->setPreference(
          PhabricatorUserPreferences::PREFERENCE_NAV_COLLAPSED,
          false);
      }

      $preferences->setPreference($pref_filetree, $filetree);

      $unified = $request->getStr($pref_unified);
      $preferences->setPreference($pref_unified, $unified);

      $ghosts = $request->getStr($pref_ghosts);
      $preferences->setPreference($pref_ghosts, $ghosts);

      $preferences->save();
      return id(new AphrontRedirectResponse())
        ->setURI($this->getPanelURI('?saved=true'));
    }

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->appendRemarkupInstructions(
        pht(
          'Phabricator normally shows diffs in a side-by-side layout on '.
          'large screens, and automatically switches to a unified '.
          'view on small screens (like mobile phones). If you prefer '.
          'unified diffs even on large screens, you can select them as '.
          'the default layout.'))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel(pht('Show Unified Diffs'))
          ->setName($pref_unified)
          ->setValue($preferences->getPreference($pref_unified))
          ->setOptions(
            array(
              'default' => pht('On Small Screens'),
              'unified' => pht('Always'),
            )))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel(pht('Show Older Inlines'))
          ->setName($pref_ghosts)
          ->setValue($preferences->getPreference($pref_ghosts))
          ->setOptions(
            array(
              'default' => pht('Enabled'),
              'disabled' => pht('Disabled'),
            )))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel(pht('Show Filetree'))
          ->setName($pref_filetree)
          ->setValue($preferences->getPreference($pref_filetree))
          ->setOptions(
            array(
              0 => pht('Disable Filetree'),
              1 => pht('Enable Filetree'),
            ))
          ->setCaption(
            pht(
              'When looking at a revision or commit, enable a sidebar '.
              'showing affected files. You can press %s to show or hide '.
              'the sidebar.',
              phutil_tag('tt', array(), 'f'))))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Save Preferences')));

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Diff Preferences'))
      ->setFormSaved($request->getBool('saved'))
      ->setForm($form);

    return array(
      $form_box,
    );
  }
}
