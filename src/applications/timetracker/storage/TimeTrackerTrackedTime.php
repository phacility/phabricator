<?php
final class TimeTrackerTrackedTime extends TimeTrackerDAO {

  protected $id;
  protected $userID;
  protected $numMinutes;
  protected $dateWhenTrackedFor;
  protected $realDateWhenTracked;

  protected function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_COLUMN_SCHEMA => array(
        'id' => 'auto',
        'userID' => 'uint32',
        'numMinutes' => 'uint32',
        'dateWhenTrackedFor' => 'uint32',
        'realDateWhenTracked' => 'uint32',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'PRIMARY' => array(
          'columns' => array('id'),
          'unique' => true,
        ),    
      ), self::CONFIG_NO_TABLE => true   
      
      );
  }
}
 // + parent::getConfiguration()