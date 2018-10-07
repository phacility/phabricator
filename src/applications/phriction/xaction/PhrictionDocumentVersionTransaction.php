<?php

abstract class PhrictionDocumentVersionTransaction
  extends PhrictionDocumentTransactionType {

  protected function getNewDocumentContent($object) {
    return $this->getEditor()->getNewDocumentContent($object);
  }

}
