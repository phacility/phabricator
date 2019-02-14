<?php

final class PhabricatorPronounSetting
  extends PhabricatorSelectSetting {

  const SETTINGKEY = 'pronoun';

  public function getSettingName() {
    return pht('Pronoun');
  }

  public function getSettingPanelKey() {
    return PhabricatorLanguageSettingsPanel::PANELKEY;
  }

  protected function getSettingOrder() {
    return 200;
  }

  protected function getControlInstructions() {
    return pht('Choose the pronoun you prefer.');
  }

  public function getSettingDefaultValue() {
    return PhutilPerson::GENDER_UNKNOWN;
  }

  protected function getSelectOptions() {
    // TODO: When editing another user's settings as an administrator, this
    // is not the best username: the user's username would be better.

    $viewer = $this->getViewer();
    $username = $viewer->getUsername();

    $label_unknown = pht('%s updated their profile', $username);
    $label_her = pht('%s updated her profile', $username);
    $label_his = pht('%s updated his profile', $username);

    return array(
      PhutilPerson::GENDER_UNKNOWN => $label_unknown,
      PhutilPerson::GENDER_MASCULINE => $label_his,
      PhutilPerson::GENDER_FEMININE => $label_her,
    );
  }

}
