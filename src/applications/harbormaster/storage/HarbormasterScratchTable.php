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

  protected function getConfiguration() {
    return array(
      self::CONFIG_COLUMN_SCHEMA => array(
        'data' => 'text64',
        'bigData' => 'text?',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'data' => array(
          'columns' => array('data'),
        ),
      ),
    ) + parent::getConfiguration();
  }

}
