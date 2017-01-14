<?php

final class DiffusionCommitDraftEngine
  extends PhabricatorDraftEngine {

  protected function hasCustomDraftContent() {
    $viewer = $this->getViewer();
    $commit = $this->getObject();

    $inlines = PhabricatorAuditInlineComment::loadDraftComments(
      $viewer,
      $commit->getPHID(),
      $raw = true);

    return (bool)$inlines;
  }

}
