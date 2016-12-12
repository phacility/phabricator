<?php

final class PhabricatorPinnedApplicationsSetting
  extends PhabricatorInternalSetting {

  const SETTINGKEY = 'app-pinned';

  public function getSettingName() {
    return pht('Pinned Applications');
  }

  public function getSettingDefaultValue() {
    $viewer = PhabricatorUser::getOmnipotentUser();

    $applications = id(new PhabricatorApplicationQuery())
      ->setViewer($viewer)
      ->withInstalled(true)
      ->withUnlisted(false)
      ->withLaunchable(true)
      ->execute();

    $pinned = array();
    foreach ($applications as $application) {
      if ($application->isPinnedByDefault($viewer)) {
        $pinned[] = get_class($application);
      }
    }

    return $pinned;
  }


}
