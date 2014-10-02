<?php

final class PhabricatorOwnersPath extends PhabricatorOwnersDAO {

  protected $packageID;
  protected $repositoryPHID;
  protected $path;
  protected $excluded;

  public function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_COLUMN_SCHEMA => array(
        'path' => 'text255',
        'excluded' => 'bool',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'packageID' => array(
          'columns' => array('packageID'),
        ),
      ),
    ) + parent::getConfiguration();
  }

}
