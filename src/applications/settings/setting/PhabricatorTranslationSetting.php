<?php

final class PhabricatorTranslationSetting
  extends PhabricatorOptionGroupSetting {

  const SETTINGKEY = 'translation';

  public function getSettingName() {
    return pht('Translation');
  }

  public function getSettingPanelKey() {
    return PhabricatorAccountSettingsPanel::PANELKEY;
  }

  protected function getSettingOrder() {
    return 100;
  }

  public function getSettingDefaultValue() {
    return 'en_US';
  }

  protected function getControlInstructions() {
    return pht(
      'Choose which language you would like the Phabricator UI to use.');
  }

  public function assertValidValue($value) {
    $locales = PhutilLocale::loadAllLocales();
    return isset($locales[$value]);
  }

  protected function getSelectOptionGroups() {
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

    // Omit silly locales on serious business installs.
    $is_serious = PhabricatorEnv::getEnvConfig('phabricator.serious-business');
    if ($is_serious) {
      unset($groups['silly']);
    }

    // Omit limited and test translations if Phabricator is not in developer
    // mode.
    $is_dev = PhabricatorEnv::getEnvConfig('phabricator.developer-mode');
    if (!$is_dev) {
      unset($groups['limited']);
      unset($groups['test']);
    }

    $results = array();
    foreach ($groups as $key => $group) {
      $label = $group_labels[$key];
      if (!$group) {
        continue;
      }

      asort($group);

      $results[] = array(
        'label' => $label,
        'options' => $group,
      );
    }

    return $results;
  }

}
