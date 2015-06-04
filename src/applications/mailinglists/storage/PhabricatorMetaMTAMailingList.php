<?php

/**
 * TODO: This class is just here to keep `storage adjust` happy until we
 * destroy the table.
 */
final class PhabricatorMetaMTAMailingList extends PhabricatorMetaMTADAO {

  protected $name;
  protected $email;
  protected $uri;


  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_COLUMN_SCHEMA => array(
        'name' => 'text128',
        'email' => 'text128',
        'uri' => 'text255?',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_phid' => null,
        'phid' => array(
          'columns' => array('phid'),
          'unique' => true,
        ),
        'email' => array(
          'columns' => array('email'),
          'unique' => true,
        ),
        'name' => array(
          'columns' => array('name'),
          'unique' => true,
        ),
      ),
    ) + parent::getConfiguration();
  }

}
