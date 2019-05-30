<?php

final class PhabricatorTimezoneSetting
  extends PhabricatorOptionGroupSetting {

  const SETTINGKEY = 'timezone';

  public function getSettingName() {
    return pht('Timezone');
  }

  public function getSettingPanelKey() {
    return PhabricatorDateTimeSettingsPanel::PANELKEY;
  }

  protected function getSettingOrder() {
    return 100;
  }

  protected function getControlInstructions() {
    return pht('Select your local timezone.');
  }

  public function getSettingDefaultValue() {
    return date_default_timezone_get();
  }

  public function assertValidValue($value) {
    // NOTE: This isn't doing anything fancy, it's just a much faster
    // validator than doing all the timezone calculations to build the full
    // list of options.

    if (!$value) {
      return;
    }

    static $identifiers;
    if ($identifiers === null) {
      $identifiers = DateTimeZone::listIdentifiers();
      $identifiers = array_fuse($identifiers);
    }

    if (isset($identifiers[$value])) {
      return;
    }

    throw new Exception(
      pht(
        'Timezone "%s" is not a valid timezone identifier.',
        $value));
  }

  protected function getSelectOptionGroups() {
    $timezones = DateTimeZone::listIdentifiers();
    $now = new DateTime('@'.PhabricatorTime::getNow());

    $groups = array();
    foreach ($timezones as $timezone) {
      $zone = new DateTimeZone($timezone);
      $offset = ($zone->getOffset($now) / 60);
      $groups[$offset][] = $timezone;
    }

    ksort($groups);

    $option_groups = array(
      array(
        'label' => pht('Default'),
        'options' => array(),
      ),
    );

    foreach ($groups as $offset => $group) {
      $hours = $offset / 60;
      $minutes = abs($offset % 60);

      if ($offset % 60) {
        $label = pht('UTC%+d:%02d', $hours, $minutes);
      } else {
        $label = pht('UTC%+d', $hours);
      }

      sort($group);

      $group_map = array();
      foreach ($group as $identifier) {
        $name = PhabricatorTime::getTimezoneDisplayName($identifier);
        $group_map[$identifier] = $name;
      }

      $option_groups[] = array(
        'label' => $label,
        'options' => $group_map,
      );
    }

    return $option_groups;
  }

  public function expandSettingTransaction($object, $xaction) {
    // When the user changes their timezone, we also clear any ignored
    // timezone offset.
    return array(
      $xaction,
      $this->newSettingTransaction(
        $object,
        PhabricatorTimezoneIgnoreOffsetSetting::SETTINGKEY,
        null),
    );
  }

}
