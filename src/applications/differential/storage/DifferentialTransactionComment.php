<?php

final class DifferentialTransactionComment
  extends PhabricatorApplicationTransactionComment {

  protected $revisionPHID;
  protected $changesetID;
  protected $isNewFile = 0;
  protected $lineNumber = 0;
  protected $lineLength = 0;
  protected $fixedState;
  protected $hasReplies = 0;
  protected $replyToCommentPHID;
  protected $legacyCommentID;

  public function getApplicationTransactionObject() {
    return new DifferentialTransaction();
  }

  public function shouldUseMarkupCache($field) {
    // Only cache submitted comments.
    return ($this->getTransactionPHID() != null);
  }
}
