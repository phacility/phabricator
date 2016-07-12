<?php

final class LegalpadTransactionComment
  extends PhabricatorApplicationTransactionComment {

  protected $documentID;
  protected $lineNumber = 0;
  protected $lineLength = 0;
  protected $fixedState;
  protected $hasReplies = 0;
  protected $replyToCommentPHID;

  public function getApplicationTransactionObject() {
    return new LegalpadTransaction();
  }

  public function shouldUseMarkupCache($field) {
    // Only cache submitted comments.
    return ($this->getTransactionPHID() != null);
  }

  protected function getConfiguration() {
    $config = parent::getConfiguration();
    $config[self::CONFIG_COLUMN_SCHEMA] = array(
      'documentID' => 'id?',
      'lineNumber' => 'uint32',
      'lineLength' => 'uint32',
      'fixedState' => 'text12?',
      'hasReplies' => 'bool',
      'replyToCommentPHID' => 'phid?',
    ) + $config[self::CONFIG_COLUMN_SCHEMA];
    $config[self::CONFIG_KEY_SCHEMA] = array(
      'key_draft' => array(
        'columns' => array('authorPHID', 'documentID', 'transactionPHID'),
        'unique' => true,
      ),
    ) + $config[self::CONFIG_KEY_SCHEMA];
    return $config;
  }

}
