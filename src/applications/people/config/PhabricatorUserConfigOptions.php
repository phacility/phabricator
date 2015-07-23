<?php

final class PhabricatorUserConfigOptions
  extends PhabricatorApplicationConfigOptions {

  public function getName() {
    return pht('User Profiles');
  }

  public function getDescription() {
    return pht('User profiles configuration.');
  }

  public function getFontIcon() {
    return 'fa-users';
  }

  public function getGroup() {
    return 'apps';
  }

  public function getOptions() {

    $default = array(
      id(new PhabricatorUserRealNameField())->getFieldKey() => true,
      id(new PhabricatorUserTitleField())->getFieldKey() => true,
      id(new PhabricatorUserSinceField())->getFieldKey() => true,
      id(new PhabricatorUserRolesField())->getFieldKey() => true,
      id(new PhabricatorUserStatusField())->getFieldKey() => true,
      id(new PhabricatorUserBlurbField())->getFieldKey() => true,
    );

    foreach ($default as $key => $enabled) {
      $default[$key] = array(
        'disabled' => !$enabled,
      );
    }

    $custom_field_type = 'custom:PhabricatorCustomFieldConfigOptionType';

    return array(
      $this->newOption('user.fields', $custom_field_type, $default)
        ->setCustomData(id(new PhabricatorUser())->getCustomFieldBaseClass())
        ->setDescription(pht('Select and reorder user profile fields.')),
      $this->newOption('user.custom-field-definitions', 'map', array())
        ->setDescription(pht('Add new simple fields to user profiles.')),
      $this->newOption('user.require-real-name', 'bool', true)
        ->setDescription(pht('Always require real name for user profiles.'))
        ->setBoolOptions(
          array(
            pht('Make real names required'),
            pht('Make real names optional'),
          )),
    );
  }

}
