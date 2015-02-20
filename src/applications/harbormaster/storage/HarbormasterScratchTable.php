<?php

/**
 * This is just a test table that unit tests can use if they need to test
 * generic database operations. It won't change and break tests and stuff, and
 * mistakes in test construction or isolation won't impact the application in
 * any way.
 */
final class HarbormasterScratchTable extends HarbormasterDAO {

  protected $data;
  protected $bigData;
  protected $nonmutableData;

  protected function getConfiguration() {
    return array(
      self::CONFIG_COLUMN_SCHEMA => array(
        'data' => 'text64',
        'bigData' => 'text?',
        'nonmutableData' => 'text64?',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'data' => array(
          'columns' => array('data'),
        ),
      ),
      self::CONFIG_NO_MUTATE => array(
        'nonmutableData',
      ),
    ) + parent::getConfiguration();
  }

}
