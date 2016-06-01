<?php

final class PhabricatorTimezoneSetting
  extends PhabricatorOptionGroupSetting {

  const SETTINGKEY = 'timezone';

  public function getSettingName() {
    return pht('Timezone');
  }

  public function getSettingDefaultValue() {
    return date_default_timezone_get();
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

}
