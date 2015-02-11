<?php

final class PhabricatorFileTransactionComment
  extends PhabricatorApplicationTransactionComment {

  public function getApplicationTransactionObject() {
    return new PhabricatorFileTransaction();
  }

  public function shouldUseMarkupCache($field) {
    // Only cache submitted comments.
    return ($this->getTransactionPHID() != null);
  }

  protected function getConfiguration() {
    $config = parent::getConfiguration();
    $config[self::CONFIG_KEY_SCHEMA] = array(
      'key_draft' => array(
        'columns' => array('authorPHID', 'transactionPHID'),
        'unique' => true,
      ),
    ) + $config[self::CONFIG_KEY_SCHEMA];
    return $config;
  }

}
