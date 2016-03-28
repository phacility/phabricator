<?php

final class PhabricatorProfilePanelEditEngine
  extends PhabricatorEditEngine {

  const ENGINECONST = 'search.profilepanel';

  private $panelEngine;
  private $profileObject;
  private $newPanelConfiguration;
  private $isBuiltin;

  public function isEngineConfigurable() {
    return false;
  }

  public function setPanelEngine(PhabricatorProfilePanelEngine $engine) {
    $this->panelEngine = $engine;
    return $this;
  }

  public function getPanelEngine() {
    return $this->panelEngine;
  }

  public function setProfileObject($profile_object) {
    $this->profileObject = $profile_object;
    return $this;
  }

  public function getProfileObject() {
    return $this->profileObject;
  }

  public function setNewPanelConfiguration(
    PhabricatorProfilePanelConfiguration $configuration) {
    $this->newPanelConfiguration = $configuration;
    return $this;
  }

  public function getNewPanelConfiguration() {
    return $this->newPanelConfiguration;
  }

  public function setIsBuiltin($is_builtin) {
    $this->isBuiltin = $is_builtin;
    return $this;
  }

  public function getIsBuiltin() {
    return $this->isBuiltin;
  }

  public function getEngineName() {
    return pht('Profile Panels');
  }

  public function getSummaryHeader() {
    return pht('Edit Profile Panel Configurations');
  }

  public function getSummaryText() {
    return pht('This engine is used to modify menu items on profiles.');
  }

  public function getEngineApplicationClass() {
    return 'PhabricatorSearchApplication';
  }

  protected function newEditableObject() {
    if (!$this->newPanelConfiguration) {
      throw new Exception(
        pht('Profile panels can not be generated without an object context.'));
    }

    return clone $this->newPanelConfiguration;
  }

  protected function newObjectQuery() {
    return id(new PhabricatorProfilePanelConfigurationQuery());
  }

  protected function getObjectCreateTitleText($object) {
    if ($this->getIsBuiltin()) {
      return pht('Edit Builtin Item');
    } else {
      return pht('Create Menu Item');
    }
  }

  protected function getObjectCreateButtonText($object) {
    if ($this->getIsBuiltin()) {
      return pht('Save Changes');
    } else {
      return pht('Create Menu Item');
    }
  }

  protected function getObjectEditTitleText($object) {
    return pht('Edit Menu Item: %s', $object->getDisplayName());
  }

  protected function getObjectEditShortText($object) {
    return pht('Edit Menu Item');
  }

  protected function getObjectCreateShortText() {
    return pht('Edit Menu Item');
  }

  protected function getObjectName() {
    return pht('Menu Item');
  }

  protected function getObjectCreateCancelURI($object) {
    return $this->getPanelEngine()->getConfigureURI();
  }

  protected function getObjectViewURI($object) {
    return $this->getPanelEngine()->getConfigureURI();
  }

  protected function buildCustomEditFields($object) {
    $panel = $object->getPanel();
    $fields = $panel->buildEditEngineFields($object);

    $type_property =
      PhabricatorProfilePanelConfigurationTransaction::TYPE_PROPERTY;

    foreach ($fields as $field) {
      $field
        ->setTransactionType($type_property)
        ->setMetadataValue('property.key', $field->getKey());
    }

    return $fields;
  }

}
