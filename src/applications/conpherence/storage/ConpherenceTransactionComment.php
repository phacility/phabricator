<?php

final class ConpherenceTransactionComment
  extends PhabricatorApplicationTransactionComment {

  protected $conpherencePHID;

  public function getApplicationTransactionObject() {
    return new ConpherenceTransaction();
  }

  public function getConfiguration() {
    $config = parent::getConfiguration();
    $config[self::CONFIG_COLUMN_SCHEMA] = array(
      'conpherencePHID' => 'phid?',
    ) + $config[self::CONFIG_COLUMN_SCHEMA];
    return $config;
  }

}
