<?php

final class DiffusionInlineCommentPreviewController
  extends PhabricatorInlineCommentPreviewController {

  protected function loadInlineComments() {
    $viewer = $this->getViewer();

    return PhabricatorAuditInlineComment::loadDraftComments(
      $viewer,
      $this->getCommitPHID());
  }

  protected function loadObjectOwnerPHID() {
    $viewer = $this->getViewer();

    $commit = id(new DiffusionCommitQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($this->getCommitPHID()))
      ->executeOne();
    if (!$commit) {
      return null;
    }

    return $commit->getAuthorPHID();
  }

  private function getCommitPHID() {
    return $this->getRequest()->getURIData('phid');
  }

}
