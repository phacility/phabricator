<?php

final class PhabricatorUserSSHKey extends PhabricatorUserDAO {

  protected $userPHID;
  protected $name;
  protected $keyType;
  protected $keyBody;
  protected $keyHash;
  protected $keyComment;

  public function getConfiguration() {
    return array(
      self::CONFIG_COLUMN_SCHEMA => array(
        'keyHash' => 'bytes32',
        'keyComment' => 'text255?',

        // T6203/NULLABILITY
        // These seem like they should not be nullable.
        'name' => 'text255?',
        'keyType' => 'text255?',
        'keyBody' => 'text?',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'userPHID' => array(
          'columns' => array('userPHID'),
        ),
        'keyHash' => array(
          'columns' => array('keyHash'),
          'unique' => true,
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function getEntireKey() {
    $parts = array(
      $this->getKeyType(),
      $this->getKeyBody(),
      $this->getKeyComment(),
    );
    return trim(implode(' ', $parts));
  }

}
