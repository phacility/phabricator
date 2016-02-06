<?php

final class DiffusionHovercardEngineExtension
  extends PhabricatorHovercardEngineExtension {

  const EXTENSIONKEY = 'diffusion';

  public function isExtensionEnabled() {
    return PhabricatorApplication::isClassInstalled(
      'PhabricatorDiffusionApplication');
  }

  public function getExtensionName() {
    return pht('Diffusion Commits');
  }

  public function canRenderObjectHovercard($object) {
    return ($object instanceof PhabricatorRepositoryCommit);
  }

  public function renderHovercard(
    PHUIHovercardView $hovercard,
    PhabricatorObjectHandle $handle,
    $commit,
    $data) {

    $viewer = $this->getViewer();

    $author_phid = $commit->getAuthorPHID();
    if ($author_phid) {
      $author = $viewer->renderHandle($author_phid);
    } else {
      $commit_data = $commit->loadCommitData();
      $author = phutil_tag('em', array(), $commit_data->getAuthorName());
    }

    $hovercard->setTitle($handle->getName());
    $hovercard->setDetail($commit->getSummary());

    $hovercard->addField(pht('Author'), $author);
    $hovercard->addField(pht('Date'),
      phabricator_date($commit->getEpoch(), $viewer));

    if ($commit->getAuditStatus() !=
      PhabricatorAuditCommitStatusConstants::NONE) {

      $hovercard->addField(pht('Audit Status'),
        PhabricatorAuditCommitStatusConstants::getStatusName(
          $commit->getAuditStatus()));
    }
  }

}
