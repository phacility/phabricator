<?php

final class PhabricatorCommentEditEngineExtension
  extends PhabricatorEditEngineExtension {

  const EXTENSIONKEY = 'transactions.comment';

  public function getExtensionPriority() {
    return 9000;
  }

  public function isExtensionEnabled() {
    return true;
  }

  public function getExtensionName() {
    return pht('Comments');
  }

  public function supportsObject(
    PhabricatorEditEngine $engine,
    PhabricatorApplicationTransactionInterface $object) {

    $xaction = $object->getApplicationTransactionTemplate();

    try {
      $comment = $xaction->getApplicationTransactionCommentObject();
    } catch (PhutilMethodNotImplementedException $ex) {
      $comment = null;
    }

    return (bool)$comment;
  }

  public function buildCustomEditFields(
    PhabricatorEditEngine $engine,
    PhabricatorApplicationTransactionInterface $object) {

    $comment_type = PhabricatorTransactions::TYPE_COMMENT;

    $comment_field = id(new PhabricatorCommentEditField())
      ->setKey('comment')
      ->setLabel(pht('Comments'))
      ->setAliases(array('comments'))
      ->setIsHidden(true)
      ->setIsReorderable(false)
      ->setIsDefaultable(false)
      ->setIsLockable(false)
      ->setTransactionType($comment_type)
      ->setConduitDescription(pht('Make comments.'))
      ->setConduitTypeDescription(
        pht('Comment to add, formatted as remarkup.'))
      ->setValue(null);

    return array(
      $comment_field,
    );
  }

}
