<?php

final class PhabricatorConpherenceSoundSetting
  extends PhabricatorSelectSetting {

  const SETTINGKEY = 'conpherence-sound';

  const VALUE_CONPHERENCE_SILENT = '0';
  const VALUE_CONPHERENCE_MENTION = '1';
  const VALUE_CONPHERENCE_ALL = '2';

  public function getSettingName() {
    return pht('Conpherence Sound');
  }

  public function getSettingPanelKey() {
    return PhabricatorConpherencePreferencesSettingsPanel::PANELKEY;
  }

  protected function getControlInstructions() {
    return pht(
      'Choose the default sound behavior for new Conpherence rooms.');
  }

  protected function isEnabledForViewer(PhabricatorUser $viewer) {
    return PhabricatorApplication::isClassInstalledForViewer(
      'PhabricatorConpherenceApplication',
      $viewer);
  }

  public function getSettingDefaultValue() {
    return self::VALUE_CONPHERENCE_ALL;
  }

  protected function getSelectOptions() {
    return self::getOptionsMap();
  }

  public static function getSettingLabel($key) {
    $labels = self::getOptionsMap();
    return idx($labels, $key, pht('Unknown ("%s")', $key));
  }

  public static function getDefaultSound($value) {
    switch ($value) {
      case self::VALUE_CONPHERENCE_ALL:
        return array(
          ConpherenceRoomSettings::SOUND_RECEIVE =>
            ConpherenceRoomSettings::DEFAULT_RECEIVE_SOUND,
          ConpherenceRoomSettings::SOUND_MENTION =>
            ConpherenceRoomSettings::DEFAULT_MENTION_SOUND,
        );
      break;
      case self::VALUE_CONPHERENCE_MENTION:
        return array(
          ConpherenceRoomSettings::SOUND_RECEIVE =>
            ConpherenceRoomSettings::DEFAULT_NO_SOUND,
          ConpherenceRoomSettings::SOUND_MENTION =>
            ConpherenceRoomSettings::DEFAULT_MENTION_SOUND,
        );
      break;
      case self::VALUE_CONPHERENCE_SILENT:
        return array(
          ConpherenceRoomSettings::SOUND_RECEIVE =>
            ConpherenceRoomSettings::DEFAULT_NO_SOUND,
          ConpherenceRoomSettings::SOUND_MENTION =>
            ConpherenceRoomSettings::DEFAULT_NO_SOUND,
        );
      break;
    }
  }

  private static function getOptionsMap() {
    return array(
      self::VALUE_CONPHERENCE_SILENT => pht('No Sounds'),
      // self::VALUE_CONPHERENCE_MENTION => pht('Mentions Only'),
      self::VALUE_CONPHERENCE_ALL => pht('All Messages'),
    );
  }

}
