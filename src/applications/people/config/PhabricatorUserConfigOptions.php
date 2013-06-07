<?php

final class PhabricatorUserConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht("User Profiles");
  }

  public function getDescription() {
    return pht("User profiles configuration.");
  }

  public function getOptions() {

    $default = array(
      id(new PhabricatorUserRealNameField())->getFieldKey() => true,
      id(new PhabricatorUserTitleField())->getFieldKey() => true,
      id(new PhabricatorUserBlurbField())->getFieldKey() => true,
    );

    foreach ($default as $key => $enabled) {
      $default[$key] = array(
        'disabled' => !$enabled,
      );
    }

    return array(
      $this->newOption('user.fields', 'wild', $default)
        ->setDescription(pht("Select and reorder user profile fields.")),
    );
  }

}
