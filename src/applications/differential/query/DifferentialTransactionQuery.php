<?php

final class DifferentialTransactionQuery
  extends PhabricatorApplicationTransactionQuery {

  public function getTemplateApplicationTransaction() {
    return new DifferentialTransaction();
  }

  public static function loadUnsubmittedInlineComments(
    PhabricatorUser $viewer,
    DifferentialRevision $revision) {

    // TODO: This probably needs to move somewhere more central as we move
    // away from DifferentialInlineCommentQuery, but
    // PhabricatorApplicationTransactionCommentQuery is currently `final` and
    // I'm not yet decided on how to approach that. For now, just get the PHIDs
    // and then execute a PHID-based query through the standard stack.

    $table = new DifferentialTransactionComment();
    $conn_r = $table->establishConnection('r');

    $phids = queryfx_all(
      $conn_r,
      'SELECT phid FROM %T
        WHERE revisionPHID = %s
          AND authorPHID = %s
          AND transactionPHID IS NULL',
      $table->getTableName(),
      $revision->getPHID(),
      $viewer->getPHID());

    $phids = ipull($phids, 'phid');
    if (!$phids) {
      return array();
    }

    return id(new PhabricatorApplicationTransactionCommentQuery())
      ->setTemplate(new DifferentialTransactionComment())
      ->setViewer($viewer)
      ->withPHIDs($phids)
      ->execute();
  }

}
