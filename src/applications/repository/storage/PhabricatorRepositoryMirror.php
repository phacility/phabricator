<?php

/**
 * TODO: Remove this class and drop the underlying table after some time has
 * passed. It currently exists only so that "bin/storage adjust" does not
 * complain about the table.
 */
final class PhabricatorRepositoryMirror
  extends PhabricatorRepositoryDAO {

  protected $repositoryPHID;
  protected $remoteURI;
  protected $credentialPHID;

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_COLUMN_SCHEMA => array(
        'remoteURI' => 'text255',
        'credentialPHID' => 'phid?',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_repository' => array(
          'columns' => array('repositoryPHID'),
        ),
      ),
    ) + parent::getConfiguration();
  }

}
