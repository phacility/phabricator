<?php

final class HeraldCommentContentField extends HeraldField {

  const FIELDCONST = 'comment.content';

  public function getHeraldFieldName() {
    return pht('Comment content');
  }

  public function getFieldGroupKey() {
    return HeraldTransactionsFieldGroup::FIELDGROUPKEY;
  }

  public function getHeraldFieldValue($object) {
    $adapter = $this->getAdapter();

    $xactions = $adapter->getAppliedTransactions();

    $result = array();
    foreach ($xactions as $xaction) {
      if (!$xaction->hasComment()) {
        continue;
      }

      $comment = $xaction->getComment();
      $content = $comment->getContent();

      $result[] = $content;
    }

    return $result;
  }

  public function supportsObject($object) {
    return true;
  }

  protected function getHeraldFieldStandardType() {
    return self::STANDARD_TEXT_LIST;
  }

}
