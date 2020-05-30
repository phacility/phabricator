<?php

final class DifferentialRevisionDraftEngine
  extends PhabricatorDraftEngine {

  protected function hasCustomDraftContent() {
    $viewer = $this->getViewer();
    $revision = $this->getObject();

    $inlines = id(new DifferentialDiffInlineCommentQuery())
      ->setViewer($viewer)
      ->withRevisionPHIDs(array($revision->getPHID()))
      ->withPublishableComments(true)
      ->setReturnPartialResultsOnOverheat(true)
      ->setLimit(1)
      ->execute();

    return (bool)$inlines;
  }

}
