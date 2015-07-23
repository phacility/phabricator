<?php

final class PhabricatorAuditTransactionComment
  extends PhabricatorApplicationTransactionComment {

  protected $commitPHID;
  protected $pathID;
  protected $isNewFile = 0;
  protected $lineNumber = 0;
  protected $lineLength = 0;
  protected $fixedState;
  protected $hasReplies = 0;
  protected $replyToCommentPHID;
  protected $legacyCommentID;

  private $replyToComment = self::ATTACHABLE;

  public function getApplicationTransactionObject() {
    return new PhabricatorAuditTransaction();
  }

  public function shouldUseMarkupCache($field) {
    // Only cache submitted comments.
    return ($this->getTransactionPHID() != null);
  }

  protected function getConfiguration() {
    $config = parent::getConfiguration();

    $config[self::CONFIG_COLUMN_SCHEMA] = array(
      'commitPHID' => 'phid?',
      'pathID' => 'id?',
      'isNewFile' => 'bool',
      'lineNumber' => 'uint32',
      'lineLength' => 'uint32',
      'fixedState' => 'text12?',
      'hasReplies' => 'bool',
      'replyToCommentPHID' => 'phid?',
      'legacyCommentID' => 'id?',
    ) + $config[self::CONFIG_COLUMN_SCHEMA];

    $config[self::CONFIG_KEY_SCHEMA] = array(
      'key_path' => array(
        'columns' => array('pathID'),
      ),
      'key_draft' => array(
        'columns' => array('authorPHID', 'transactionPHID'),
      ),
      'key_commit' => array(
        'columns' => array('commitPHID'),
      ),
      'key_legacy' => array(
        'columns' => array('legacyCommentID'),
      ),
    ) + $config[self::CONFIG_KEY_SCHEMA];

    return $config;
  }

  public function attachReplyToComment(
    PhabricatorAuditTransactionComment $comment = null) {
    $this->replyToComment = $comment;
    return $this;
  }

  public function getReplyToComment() {
    return $this->assertAttached($this->replyToComment);
  }

}
