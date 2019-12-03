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

    $commit = id(new DiffusionCommitQuery())
      ->setViewer($viewer)
      ->needIdentities(true)
      ->needCommitData(true)
      ->withPHIDs(array($commit->getPHID()))
      ->executeOne();
    if (!$commit) {
      return;
    }

    $author_phid = $commit->getAuthorDisplayPHID();
    $committer_phid = $commit->getCommitterDisplayPHID();
    $repository_phid = $commit->getRepository()->getPHID();

    $phids = array();
    $phids[] = $author_phid;
    $phids[] = $committer_phid;
    $phids[] = $repository_phid;

    $handles = $viewer->loadHandles($phids);

    $hovercard->setTitle($handle->getName());
    $hovercard->setDetail($commit->getSummary());

    $repository = $handles[$repository_phid]->renderLink();
    $hovercard->addField(pht('Repository'), $repository);

    $author = $handles[$author_phid]->renderLink();
    if ($author_phid) {
      $hovercard->addField(pht('Author'), $author);
    }

    if ($committer_phid && ($committer_phid !== $author_phid)) {
      $committer = $handles[$committer_phid]->renderLink();
      $hovercard->addField(pht('Committer'), $committer);
    }

    $date = phabricator_date($commit->getEpoch(), $viewer);
    $hovercard->addField(pht('Date'), $date);

    if (!$commit->isAuditStatusNoAudit()) {
      $status = $commit->getAuditStatusObject();

      $hovercard->addField(
        pht('Audit Status'),
        $status->getName());
    }
  }

}
