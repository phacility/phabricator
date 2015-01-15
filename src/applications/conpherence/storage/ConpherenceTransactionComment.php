<?php

final class ConpherenceTransactionComment
  extends PhabricatorApplicationTransactionComment {

  protected $conpherencePHID;

  public function getApplicationTransactionObject() {
    return new ConpherenceTransaction();
  }

  protected function getConfiguration() {
    $config = parent::getConfiguration();

    $config[self::CONFIG_COLUMN_SCHEMA] = array(
      'conpherencePHID' => 'phid?',
    ) + $config[self::CONFIG_COLUMN_SCHEMA];

    $config[self::CONFIG_KEY_SCHEMA] = array(
      'key_draft' => array(
        'columns' => array('authorPHID', 'conpherencePHID', 'transactionPHID'),
        'unique' => true,
      ),
    ) + $config[self::CONFIG_KEY_SCHEMA];

    return $config;
  }

}
