<?php

final class DifferentialTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  public function getTemplateApplicationTransaction() {
    return new DifferentialTransaction();
  }

  public static function loadUnsubmittedInlineComments(
    PhabricatorUser $viewer,
    DifferentialRevision $revision) {

    // TODO: Subclass ApplicationTransactionCommentQuery to do this for real.

    $table = new DifferentialTransactionComment();
    $conn_r = $table->establishConnection('r');

    $phids = queryfx_all(
      $conn_r,
      'SELECT phid FROM %T
        WHERE revisionPHID = %s
          AND authorPHID = %s
          AND transactionPHID IS NULL
          AND isDeleted = 0',
      $table->getTableName(),
      $revision->getPHID(),
      $viewer->getPHID());

    $phids = ipull($phids, 'phid');
    if (!$phids) {
      return array();
    }

    $comments = id(new PhabricatorApplicationTransactionTemplatedCommentQuery())
      ->setTemplate(new DifferentialTransactionComment())
      ->setViewer($viewer)
      ->withPHIDs($phids)
      ->execute();

    $comments = PhabricatorInlineCommentController::loadAndAttachReplies(
      $viewer,
      $comments);

    return $comments;
  }

}
