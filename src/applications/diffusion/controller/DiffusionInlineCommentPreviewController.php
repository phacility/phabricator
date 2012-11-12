<?php

final class DiffusionInlineCommentPreviewController
  extends PhabricatorInlineCommentPreviewController {

  private $commitPHID;

  public function willProcessRequest(array $data) {
    $this->commitPHID = $data['phid'];
  }

  protected function loadInlineComments() {
    $user = $this->getRequest()->getUser();

    $inlines = id(new PhabricatorAuditInlineComment())->loadAllWhere(
      'authorPHID = %s AND commitPHID = %s AND auditCommentID IS NULL',
      $user->getPHID(),
      $this->commitPHID);

    return $inlines;
  }
}
