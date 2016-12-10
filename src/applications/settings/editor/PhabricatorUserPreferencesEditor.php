<?php

final class PhabricatorUserPreferencesEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getEditorApplicationClass() {
    return 'PhabricatorSettingsApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('Settings');
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = PhabricatorUserPreferencesTransaction::TYPE_SETTING;

    return $types;
  }

  protected function expandTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    $setting_key = $xaction->getMetadataValue(
      PhabricatorUserPreferencesTransaction::PROPERTY_SETTING);

    $settings = $this->getSettings();
    $setting = idx($settings, $setting_key);
    if ($setting) {
      return $setting->expandSettingTransaction($object, $xaction);
    }

    return parent::expandTransaction($object, $xaction);
  }


  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    $setting_key = $xaction->getMetadataValue(
      PhabricatorUserPreferencesTransaction::PROPERTY_SETTING);

    switch ($xaction->getTransactionType()) {
      case PhabricatorUserPreferencesTransaction::TYPE_SETTING:
        return $object->getPreference($setting_key);
    }

    return parent::getCustomTransactionOldValue($object, $xaction);
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    $actor = $this->getActor();

    $setting_key = $xaction->getMetadataValue(
      PhabricatorUserPreferencesTransaction::PROPERTY_SETTING);

    $settings = PhabricatorSetting::getAllEnabledSettings($actor);
    $setting = $settings[$setting_key];

    switch ($xaction->getTransactionType()) {
      case PhabricatorUserPreferencesTransaction::TYPE_SETTING:
        $value = $xaction->getNewValue();
        $value = $setting->getTransactionNewValue($value);
        return $value;
    }

    return parent::getCustomTransactionNewValue($object, $xaction);
  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    $setting_key = $xaction->getMetadataValue(
      PhabricatorUserPreferencesTransaction::PROPERTY_SETTING);

    switch ($xaction->getTransactionType()) {
      case PhabricatorUserPreferencesTransaction::TYPE_SETTING:
        $new_value = $xaction->getNewValue();
        if ($new_value === null) {
          $object->unsetPreference($setting_key);
        } else {
          $object->setPreference($setting_key, $new_value);
        }
        return;
    }

    return parent::applyCustomInternalTransaction($object, $xaction);
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorUserPreferencesTransaction::TYPE_SETTING:
        return;
    }

    return parent::applyCustomExternalTransaction($object, $xaction);
  }

  protected function validateTransaction(
    PhabricatorLiskDAO $object,
    $type,
    array $xactions) {

    $errors = parent::validateTransaction($object, $type, $xactions);
    $settings = $this->getSettings();

    switch ($type) {
      case PhabricatorUserPreferencesTransaction::TYPE_SETTING:
        foreach ($xactions as $xaction) {
          $setting_key = $xaction->getMetadataValue(
            PhabricatorUserPreferencesTransaction::PROPERTY_SETTING);

          $setting = idx($settings, $setting_key);
          if (!$setting) {
            $errors[] = new PhabricatorApplicationTransactionValidationError(
              $type,
              pht('Invalid'),
              pht(
                'There is no known application setting with key "%s".',
                $setting_key),
              $xaction);
            continue;
          }

          try {
            $setting->validateTransactionValue($xaction->getNewValue());
          } catch (Exception $ex) {
            $errors[] = new PhabricatorApplicationTransactionValidationError(
              $type,
              pht('Invalid'),
              $ex->getMessage(),
              $xaction);
          }
        }
        break;
    }

    return $errors;
  }

  protected function applyFinalEffects(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $user_phid = $object->getUserPHID();
    if ($user_phid) {
      PhabricatorUserCache::clearCache(
        PhabricatorUserPreferencesCacheType::KEY_PREFERENCES,
        $user_phid);
    } else {
      $cache = PhabricatorCaches::getMutableStructureCache();
      $cache->deleteKey(PhabricatorUserPreferences::getGlobalCacheKey());

      PhabricatorUserCache::clearCacheForAllUsers(
        PhabricatorUserPreferencesCacheType::KEY_PREFERENCES);
    }

    return $xactions;
  }

  private function getSettings() {
    $actor = $this->getActor();
    $settings = PhabricatorSetting::getAllEnabledSettings($actor);

    foreach ($settings as $key => $setting) {
      $setting = clone $setting;
      $setting->setViewer($actor);
      $settings[$key] = $setting;
    }

    return $settings;
  }

}
