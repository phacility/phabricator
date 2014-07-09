<?php

final class PhabricatorPasteTransactionComment
  extends PhabricatorApplicationTransactionComment {

  protected $lineNumber;
  protected $lineLength;

  public function getApplicationTransactionObject() {
    return new PhabricatorPasteTransaction();
  }

  public function shouldUseMarkupCache($field) {
    // Only cache submitted comments.
    return ($this->getTransactionPHID() != null);
  }

}
