<?php

final class PhabricatorSettingsEditEngine
  extends PhabricatorEditEngine {

  const ENGINECONST = 'settings.settings';

  public function isEngineConfigurable() {
    return false;
  }

  public function getEngineName() {
    return pht('Settings');
  }

  public function getSummaryHeader() {
    return pht('Edit Settings Configurations');
  }

  public function getSummaryText() {
    return pht('This engine is used to edit settings.');
  }

  public function getEngineApplicationClass() {
    return 'PhabricatorSettingsApplication';
  }

  protected function newEditableObject() {
    return new PhabricatorUserPreferences();
  }

  protected function newObjectQuery() {
    return new PhabricatorUserPreferencesQuery();
  }

  protected function getObjectCreateTitleText($object) {
    return pht('Create Settings');
  }

  protected function getObjectCreateButtonText($object) {
    return pht('Create Settings');
  }

  protected function getObjectEditTitleText($object) {
    return pht('Edit Settings');
  }

  protected function getObjectEditShortText($object) {
    return pht('Edit Settings');
  }

  protected function getObjectCreateShortText() {
    return pht('Create Settings');
  }

  protected function getObjectName() {
    return pht('Settings');
  }

  protected function getEditorURI() {
    return '/settings/edit/';
  }

  protected function getObjectCreateCancelURI($object) {
    return '/settings/';
  }

  protected function getObjectViewURI($object) {
    // TODO: This isn't correct...
    return '/settings/user/'.$this->getViewer()->getUsername().'/';
  }

  protected function getCreateNewObjectPolicy() {
    return PhabricatorPolicies::POLICY_ADMIN;
  }

  protected function buildCustomEditFields($object) {
    $viewer = $this->getViewer();
    $settings = PhabricatorSetting::getAllEnabledSettings($viewer);

    $fields = array();
    foreach ($settings as $setting) {
      foreach ($setting->newCustomEditFields($object) as $field) {
        $fields[] = $field;
      }
    }

    return $fields;
  }

}
