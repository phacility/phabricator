<?php

final class DifferentialRevisionDraftEngine
  extends PhabricatorDraftEngine {

  protected function hasCustomDraftContent() {
    $viewer = $this->getViewer();
    $revision = $this->getObject();

    $inlines = DifferentialTransactionQuery::loadUnsubmittedInlineComments(
      $viewer,
      $revision);

    return (bool)$inlines;
  }

}
