<?php

final class PhabricatorSettingsEditEngine
  extends PhabricatorEditEngine {

  const ENGINECONST = 'settings.settings';

  private $isSelfEdit;
  private $profileURI;
  private $settingsPanel;

  public function setIsSelfEdit($is_self_edit) {
    $this->isSelfEdit = $is_self_edit;
    return $this;
  }

  public function getIsSelfEdit() {
    return $this->isSelfEdit;
  }

  public function setProfileURI($profile_uri) {
    $this->profileURI = $profile_uri;
    return $this;
  }

  public function getProfileURI() {
    return $this->profileURI;
  }

  public function setSettingsPanel($settings_panel) {
    $this->settingsPanel = $settings_panel;
    return $this;
  }

  public function getSettingsPanel() {
    return $this->settingsPanel;
  }

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
    $page = $this->getSelectedPage();

    if ($page) {
      return $page->getLabel();
    }

    return pht('Settings');
  }

  protected function getObjectEditShortText($object) {
    if (!$object->getUser()) {
      return pht('Global Defaults');
    } else {
      if ($this->getIsSelfEdit()) {
        return pht('Personal Settings');
      } else {
        return pht('Account Settings');
      }
    }
  }

  protected function getObjectCreateShortText() {
    return pht('Create Settings');
  }

  protected function getObjectName() {
    $page = $this->getSelectedPage();

    if ($page) {
      return $page->getLabel();
    }

    return pht('Settings');
  }

  protected function getPageHeader($object) {
    $user = $object->getUser();
    if ($user) {
      $text = pht('Edit Settings: %s', $user->getUserName());
    } else {
      $text = pht('Edit Global Settings');
    }

    $header = id(new PHUIHeaderView())
      ->setHeader($text);

    return $header;
  }

  protected function getEditorURI() {
    throw new PhutilMethodNotImplementedException();
  }

  protected function getObjectCreateCancelURI($object) {
    return '/settings/';
  }

  protected function getObjectViewURI($object) {
    return $object->getEditURI();
  }

  protected function getCreateNewObjectPolicy() {
    return PhabricatorPolicies::POLICY_ADMIN;
  }

  public function getEffectiveObjectEditCancelURI($object) {
    if (!$object->getUser()) {
      return '/settings/';
    }

    if ($this->getIsSelfEdit()) {
      return null;
    }

    if ($this->getProfileURI()) {
      return $this->getProfileURI();
    }

    return parent::getEffectiveObjectEditCancelURI($object);
  }

  protected function newPages($object) {
    $viewer = $this->getViewer();
    $user = $object->getUser();

    $panels = PhabricatorSettingsPanel::getAllDisplayPanels();

    foreach ($panels as $key => $panel) {
      if (!($panel instanceof PhabricatorEditEngineSettingsPanel)) {
        unset($panels[$key]);
        continue;
      }

      $panel->setViewer($viewer);
      if ($user) {
        $panel->setUser($user);
      }
    }

    $pages = array();
    $uris = array();
    foreach ($panels as $key => $panel) {
      $uris[$key] = $panel->getPanelURI();

      $page = $panel->newEditEnginePage();
      if (!$page) {
        continue;
      }
      $pages[] = $page;
    }

    $more_pages = array(
      id(new PhabricatorEditPage())
        ->setKey('extra')
        ->setLabel(pht('Extra Settings'))
        ->setIsDefault(true),
    );

    foreach ($more_pages as $page) {
      $pages[] = $page;
    }

    return $pages;
  }

  protected function buildCustomEditFields($object) {
    $viewer = $this->getViewer();
    $settings = PhabricatorSetting::getAllEnabledSettings($viewer);

    foreach ($settings as $key => $setting) {
      $setting = clone $setting;
      $setting->setViewer($viewer);
      $settings[$key] = $setting;
    }

    $settings = msortv($settings, 'getSettingOrderVector');

    $fields = array();
    foreach ($settings as $setting) {
      foreach ($setting->newCustomEditFields($object) as $field) {
        $fields[] = $field;
      }
    }

    return $fields;
  }

  protected function getValidationExceptionShortMessage(
    PhabricatorApplicationTransactionValidationException $ex,
    PhabricatorEditField $field) {

    // Settings fields all have the same transaction type so we need to make
    // sure the transaction is changing the same setting before matching an
    // error to a given field.
    $xaction_type = $field->getTransactionType();
    if ($xaction_type == PhabricatorUserPreferencesTransaction::TYPE_SETTING) {
      $property = PhabricatorUserPreferencesTransaction::PROPERTY_SETTING;

      $field_setting = idx($field->getMetadata(), $property);
      foreach ($ex->getErrors() as $error) {
        if ($error->getType() !== $xaction_type) {
          continue;
        }

        $xaction = $error->getTransaction();
        if (!$xaction) {
          continue;
        }

        $xaction_setting = $xaction->getMetadataValue($property);
        if ($xaction_setting != $field_setting) {
          continue;
        }

        $short_message = $error->getShortMessage();
        if ($short_message !== null) {
          return $short_message;
        }
      }

      return null;
    }

    return parent::getValidationExceptionShortMessage($ex, $field);
  }

  protected function newEditFormHeadContent(
    PhabricatorEditEnginePageState $state) {

    $content = array();

    if ($state->getIsSave()) {
      $content[] = id(new PHUIInfoView())
        ->setSeverity(PHUIInfoView::SEVERITY_NOTICE)
        ->appendChild(pht('Changes saved.'));
    }

    $panel = $this->getSettingsPanel();
    $content[] = $panel->newSettingsPanelEditFormHeadContent($state);

    return $content;
  }

  protected function newEditFormTailContent(
    PhabricatorEditEnginePageState $state) {

    $content = array();

    $panel = $this->getSettingsPanel();
    $content[] = $panel->newSettingsPanelEditFormTailContent($state);

    return $content;
  }

}
