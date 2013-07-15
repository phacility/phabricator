<?php

/**
 * @group legalpad
 */
final class LegalpadTransactionComment
  extends PhabricatorApplicationTransactionComment {

  protected $documentID;
  protected $lineNumber;
  protected $lineLength;
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
}
