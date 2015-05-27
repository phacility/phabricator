<?php

final class DifferentialInlineCommentPreviewController
extends PhabricatorInlineCommentPreviewController {

  protected function loadInlineComments() {
    $viewer = $this->getViewer();

    $revision = id(new DifferentialRevisionQuery())
      ->setViewer($viewer)
      ->withIDs(array($this->getRevisionID()))
      ->executeOne();
    if (!$revision) {
      return array();
    }

    return id(new DifferentialInlineCommentQuery())
      ->setViewer($this->getViewer())
      ->withDrafts(true)
      ->withAuthorPHIDs(array($viewer->getPHID()))
      ->withRevisionPHIDs(array($revision->getPHID()))
      ->needHidden(true)
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
