<?php

final class PhabricatorAccessibilitySetting
  extends PhabricatorSelectSetting {

  const SETTINGKEY = 'resource-postprocessor';

  public function getSettingName() {
    return pht('Accessibility');
  }

  public function getSettingPanelKey() {
    return PhabricatorDisplayPreferencesSettingsPanel::PANELKEY;
  }

  protected function getSettingOrder() {
    return 100;
  }

  protected function getControlInstructions() {
    return pht(
      'If you have difficulty reading the Phabricator UI, this setting '.
      'may make Phabricator more accessible.');
  }

  public function getSettingDefaultValue() {
    return CelerityDefaultPostprocessor::POSTPROCESSOR_KEY;
  }

  protected function getSelectOptions() {
    $postprocessor_map = CelerityPostprocessor::getAllPostprocessors();

    $postprocessor_map = mpull($postprocessor_map, 'getPostprocessorName');
    asort($postprocessor_map);

    $postprocessor_order = array(
      CelerityDefaultPostprocessor::POSTPROCESSOR_KEY,
    );

    $postprocessor_map = array_select_keys(
      $postprocessor_map,
      $postprocessor_order) + $postprocessor_map;

    return $postprocessor_map;
  }

}
