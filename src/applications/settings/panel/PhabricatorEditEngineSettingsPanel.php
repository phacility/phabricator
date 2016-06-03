<?php

abstract class PhabricatorEditEngineSettingsPanel
  extends PhabricatorSettingsPanel {

  final public function processRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $user = $this->getUser();

    if ($user->getPHID() === $viewer->getPHID()) {
      $is_self = true;
    } else {
      $is_self = false;
    }

    if ($user->getPHID()) {
      $profile_uri = '/people/manage/'.$user->getID().'/';
    } else {
      $profile_uri = null;
    }

    $engine = id(new PhabricatorSettingsEditEngine())
      ->setController($this->getController())
      ->setNavigation($this->getNavigation())
      ->setHideHeader(true)
      ->setIsSelfEdit($is_self)
      ->setProfileURI($profile_uri);

    $preferences = $user->loadPreferences();

    PhabricatorPolicyFilter::requireCapability(
      $viewer,
      $preferences,
      PhabricatorPolicyCapability::CAN_EDIT);

    $engine->setTargetObject($preferences);

    return $engine->buildResponse();
  }

  final public function newEditEnginePage() {
    $field_keys = $this->getPanelSettingsKeys();
    if (!$field_keys) {
      return null;
    }

    $key = $this->getPanelKey();
    $label = $this->getPanelName();
    $panel_uri = $this->getPanelURI().'saved/';

    return id(new PhabricatorEditPage())
      ->setKey($key)
      ->setLabel($label)
      ->setViewURI($panel_uri)
      ->setFieldKeys($field_keys);
  }

  final public function getPanelSettingsKeys() {
    $viewer = $this->getViewer();
    $settings = PhabricatorSetting::getAllEnabledSettings($viewer);

    $this_key = $this->getPanelKey();

    $panel_settings = array();
    foreach ($settings as $setting) {
      if ($setting->getSettingPanelKey() == $this_key) {
        $panel_settings[] = $setting;
      }
    }

    return mpull($panel_settings, 'getSettingKey');
  }

}
