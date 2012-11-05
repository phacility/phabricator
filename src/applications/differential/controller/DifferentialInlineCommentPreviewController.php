<?php

final class DifferentialInlineCommentPreviewController
extends PhabricatorInlineCommentPreviewController {

  private $revisionID;

  public function willProcessRequest(array $data) {
    $this->revisionID = $data['id'];
  }

  protected function loadInlineComments() {
    $user = $this->getRequest()->getUser();

    $inlines = id(new DifferentialInlineComment())->loadAllWhere(
      'authorPHID = %s AND revisionID = %d AND commentID IS NULL',
      $user->getPHID(),
      $this->revisionID);

    return $inlines;
  }

}
