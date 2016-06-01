<?php

final class PhabricatorPronounSetting
  extends PhabricatorSelectSetting {

  const SETTINGKEY = 'pronoun';

  public function getSettingName() {
    return pht('Pronoun');
  }

  protected function getControlInstructions() {
    return pht('Choose the pronoun you prefer.');
  }

  public function getSettingDefaultValue() {
    return PhutilPerson::SEX_UNKNOWN;
  }

  protected function getSelectOptions() {
    $viewer = $this->getViewer();
    $username = $viewer->getUsername();

    $label_unknown = pht('%s updated their profile', $username);
    $label_her = pht('%s updated her profile', $username);
    $label_his = pht('%s updated his profile', $username);

    return array(
      PhutilPerson::SEX_UNKNOWN => $label_unknown,
      PhutilPerson::SEX_MALE => $label_his,
      PhutilPerson::SEX_FEMALE => $label_her,
    );
  }

}
