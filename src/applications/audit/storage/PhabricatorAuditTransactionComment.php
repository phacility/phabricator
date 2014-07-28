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

  public function getApplicationTransactionObject() {
    return new PhabricatorAuditTransaction();
  }

  public function shouldUseMarkupCache($field) {
    // Only cache submitted comments.
    return ($this->getTransactionPHID() != null);
  }

}
