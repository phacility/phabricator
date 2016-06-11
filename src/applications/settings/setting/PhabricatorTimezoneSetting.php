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
        'Timezone "%s" is not a valid timezone identiifer.',
        $value));
  }

  protected function getSelectOptionGroups() {
    $timezones = DateTimeZone::listIdentifiers();
    $now = new DateTime('@'.PhabricatorTime::getNow());

    $groups = array();
    foreach ($timezones as $timezone) {
      $zone = new DateTimeZone($timezone);
      $offset = -($zone->getOffset($now) / (60 * 60));
      $groups[$offset][] = $timezone;
    }

    krsort($groups);

    $option_groups = array(
      array(
        'label' => pht('Default'),
        'options' => array(),
      ),
    );

    foreach ($groups as $offset => $group) {
      if ($offset >= 0) {
        $label = pht('UTC-%d', $offset);
      } else {
        $label = pht('UTC+%d', -$offset);
      }

      sort($group);
      $option_groups[] = array(
        'label' => $label,
        'options' => array_fuse($group),
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
