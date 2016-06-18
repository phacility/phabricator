<?php

final class PhabricatorCoreCreateTransaction
  extends PhabricatorCoreTransactionType {

  const TRANSACTIONTYPE = 'core:create';

  public function generateOldValue($object) {
    return null;
  }

  public function getTitle() {
    $editor = $this->getObject()->getApplicationTransactionEditor();

    $author = $this->renderAuthor();
    $object = $this->renderObject();

    return $editor->getCreateObjectTitle($author, $object);
  }

  public function getTitleForFeed() {
    $editor = $this->getObject()->getApplicationTransactionEditor();

    $author = $this->renderAuthor();
    $object = $this->renderObject();

    return $editor->getCreateObjectTitleForFeed($author, $object);
  }

}
