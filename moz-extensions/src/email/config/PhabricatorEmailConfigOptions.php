<?php

final class PhabricatorEmailConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht('Email');
  }

  public function getDescription() {
    return pht('Adjust notification emails.');
  }

  public function getIcon() {
    return 'fa-cog';
  }

  public function getGroup() {
    return 'apps';
  }

  public function getOptions() {
    return array(
      $this->newOption(
        'email.default',
        'enum',
        PhabricatorEmailNotificationsSetting::VALUE_SEND_MAIL)
        ->setDescription(pht('Sets the default "Email Notifications" user option.'))
        ->setEnumOptions(PhabricatorEmailNotificationsSetting::getOptions()),
    );
  }
}
