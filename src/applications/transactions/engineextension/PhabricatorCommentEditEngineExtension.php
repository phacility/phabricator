<?php

final class PhabricatorCommentEditEngineExtension
  extends PhabricatorEditEngineExtension {

  const EXTENSIONKEY = 'transactions.comment';
  const EDITKEY = 'comment';

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
    $comment = $xaction->getApplicationTransactionCommentObject();

    return (bool)$comment;
  }

  public function newBulkEditGroups(PhabricatorEditEngine $engine) {
    return array(
      id(new PhabricatorBulkEditGroup())
        ->setKey('comments')
        ->setLabel(pht('Comments')),
    );
  }

  public function buildCustomEditFields(
    PhabricatorEditEngine $engine,
    PhabricatorApplicationTransactionInterface $object) {

    $comment_type = PhabricatorTransactions::TYPE_COMMENT;

    // Comments have a lot of special behavior which doesn't always check
    // this flag, but we set it for consistency.
    $is_interact = true;

    $comment_field = id(new PhabricatorCommentEditField())
      ->setKey(self::EDITKEY)
      ->setLabel(pht('Comments'))
      ->setBulkEditLabel(pht('Add comment'))
      ->setBulkEditGroupKey('comments')
      ->setAliases(array('comments'))
      ->setIsFormField(false)
      ->setCanApplyWithoutEditCapability($is_interact)
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
