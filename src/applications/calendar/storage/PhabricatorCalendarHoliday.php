<?php

final class PhabricatorCalendarHoliday extends PhabricatorCalendarDAO {

  protected $day;
  protected $name;

  protected function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_COLUMN_SCHEMA => array(
        'day' => 'date',
        'name' => 'text64',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'day' => array(
          'columns' => array('day'),
          'unique' => true,
        ),
      ),
    ) + parent::getConfiguration();
  }

}
