<?php

final class PhabricatorAccountSettingsPanel extends PhabricatorSettingsPanel {

  public function getPanelKey() {
    return 'account';
  }

  public function getPanelName() {
    return pht('Account');
  }

  public function getPanelGroup() {
    return pht('Account Information');
  }

  public function isEditableByAdministrators() {
    return true;
  }

  public function processRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $user = $this->getUser();
    $username = $user->getUsername();

    $errors = array();
    if ($request->isFormPost()) {
      $sex = $request->getStr('sex');
      $sexes = array(PhutilPerson::SEX_MALE, PhutilPerson::SEX_FEMALE);
      if (in_array($sex, $sexes)) {
        $user->setSex($sex);
      } else {
        $user->setSex(null);
      }

      // Checked in runtime.
      $user->setTranslation($request->getStr('translation'));

      if (!$errors) {
        $user->save();
        return id(new AphrontRedirectResponse())
          ->setURI($this->getPanelURI('?saved=true'));
      }
    }

    $label_unknown = pht('%s updated their profile', $username);
    $label_her = pht('%s updated her profile', $username);
    $label_his = pht('%s updated his profile', $username);

    $sexes = array(
      PhutilPerson::SEX_UNKNOWN => $label_unknown,
      PhutilPerson::SEX_MALE => $label_his,
      PhutilPerson::SEX_FEMALE => $label_her,
    );

    $translations = $this->getTranslationOptions();

    $form = new AphrontFormView();
    $form
      ->setUser($viewer)
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setOptions($translations)
          ->setLabel(pht('Translation'))
          ->setName('translation')
          ->setValue($user->getTranslation()))
      ->appendRemarkupInstructions(pht('**Choose the pronoun you prefer:**'))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setOptions($sexes)
          ->setLabel(pht('Pronoun'))
          ->setName('sex')
          ->setValue($user->getSex()))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Save Account Settings')));

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Account Settings'))
      ->setFormSaved($request->getStr('saved'))
      ->setFormErrors($errors)
      ->setForm($form);

    return array(
      $form_box,
    );
  }

  private function getTranslationOptions() {
    $is_serious = PhabricatorEnv::getEnvConfig('phabricator.serious-business');
    $locales = PhutilLocale::loadAllLocales();

    $group_labels = array(
      'normal' => pht('Translations'),
      'limited' => pht('Limited Translations'),
      'silly' => pht('Silly Translations'),
      'test' => pht('Developer/Test Translations'),
    );

    $groups = array_fill_keys(array_keys($group_labels), array());

    $translations = array();
    foreach ($locales as $locale) {
      $code = $locale->getLocaleCode();

      // Get the locale's localized name if it's available. For example,
      // "Deutsch" instead of "German". This helps users who do not speak the
      // current language to find the correct setting.
      $raw_scope = PhabricatorEnv::beginScopedLocale($code);
      $name = $locale->getLocaleName();
      unset($raw_scope);

      if ($locale->isSillyLocale()) {
        if ($is_serious) {
          // Omit silly locales on serious business installs.
          continue;
        }
        $groups['silly'][$code] = $name;
        continue;
      }

      if ($locale->isTestLocale()) {
        $groups['test'][$code] = $name;
        continue;
      }

      $strings = PhutilTranslation::getTranslationMapForLocale($code);
      $size = count($strings);

      // If a translation is English, assume it can fall back to the default
      // strings and don't caveat its completeness.
      $is_english = (substr($code, 0, 3) == 'en_');

      // Arbitrarily pick some number of available strings to promote a
      // translation out of the "limited" group. The major goal is just to
      // keep locales with very few strings out of the main group, so users
      // aren't surprised if a locale has no upstream translations available.
      if ($size > 512 || $is_english) {
        $type = 'normal';
      } else {
        $type = 'limited';
      }

      $groups[$type][$code] = $name;
    }

    // TODO: Select a default properly.
    $default = 'en_US';

    $results = array();
    foreach ($groups as $key => $group) {
      $label = $group_labels[$key];
      if (!$group) {
        continue;
      }

      asort($group);

      if ($key == 'normal') {
        $group = array(
          '' => pht('Server Default: %s', $locales[$default]->getLocaleName()),
        ) + $group;
      }

      $results[$label] = $group;
    }

    return $results;
  }

}
