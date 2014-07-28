<?php

final class DiffusionInlineCommentPreviewController
  extends PhabricatorInlineCommentPreviewController {

  private $commitPHID;

  public function willProcessRequest(array $data) {
    $this->commitPHID = $data['phid'];
  }

  protected function loadInlineComments() {
    $user = $this->getRequest()->getUser();

    $inlines = PhabricatorAuditInlineComment::loadDraftComments(
      $user,
      $this->commitPHID);

    return $inlines;
  }
}
