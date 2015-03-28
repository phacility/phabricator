<?php

final class DifferentialInlineCommentPreviewController
extends PhabricatorInlineCommentPreviewController {

  protected function loadInlineComments() {
    $viewer = $this->getViewer();

    return id(new DifferentialInlineCommentQuery())
      ->withDraftComments($viewer->getPHID(), $this->getRevisionID())
      ->execute();
  }

  protected function loadObjectOwnerPHID() {
    $viewer = $this->getViewer();

    $revision = id(new DifferentialRevisionQuery())
      ->setViewer($viewer)
      ->withIDs(array($this->getRevisionID()))
      ->executeOne();
    if (!$revision) {
      return null;
    }

    return $revision->getAuthorPHID();
  }


  private function getRevisionID() {
    return $this->getRequest()->getURIData('id');
  }
}
