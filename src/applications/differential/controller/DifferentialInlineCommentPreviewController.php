<?php

final class DifferentialInlineCommentPreviewController
extends PhabricatorInlineCommentPreviewController {

  private $revisionID;

  public function willProcessRequest(array $data) {
    $this->revisionID = $data['id'];
  }

  protected function loadInlineComments() {
    $user = $this->getRequest()->getUser();

    $inlines = id(new DifferentialInlineCommentQuery())
      ->withDraftComments($user->getPHID(), $this->revisionID)
      ->execute();

    return $inlines;
  }

}
