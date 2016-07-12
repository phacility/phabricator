<?php

final class DifferentialHovercardEngineExtension
  extends PhabricatorHovercardEngineExtension {

  const EXTENSIONKEY = 'differential';

  public function isExtensionEnabled() {
    return PhabricatorApplication::isClassInstalled(
      'PhabricatorDifferentialApplication');
  }

  public function getExtensionName() {
    return pht('Differential Revisions');
  }

  public function canRenderObjectHovercard($object) {
    return ($object instanceof DifferentialRevision);
  }

  public function willRenderHovercards(array $objects) {
    $viewer = $this->getViewer();
    $phids = mpull($objects, 'getPHID');

    $revisions = id(new DifferentialRevisionQuery())
      ->setViewer($viewer)
      ->withPHIDs($phids)
      ->needReviewerStatus(true)
      ->execute();
    $revisions = mpull($revisions, null, 'getPHID');

    return array(
      'revisions' => $revisions,
    );
  }

  public function renderHovercard(
    PHUIHovercardView $hovercard,
    PhabricatorObjectHandle $handle,
    $object,
    $data) {

    $viewer = $this->getViewer();

    $revision = idx($data['revisions'], $object->getPHID());
    if (!$revision) {
      return;
    }

    $hovercard->setTitle('D'.$revision->getID());
    $hovercard->setDetail($revision->getTitle());

    $hovercard->addField(
      pht('Author'),
      $viewer->renderHandle($revision->getAuthorPHID()));

    $reviewer_phids = $revision->getReviewerStatus();
    $reviewer_phids = mpull($reviewer_phids, 'getReviewerPHID');

    $hovercard->addField(
      pht('Reviewers'),
      $viewer->renderHandleList($reviewer_phids)->setAsInline(true));

    $summary = $revision->getSummary();
    if (strlen($summary)) {
      $summary = id(new PhutilUTF8StringTruncator())
        ->setMaximumGlyphs(120)
        ->truncateString($summary);

      $hovercard->addField(pht('Summary'), $summary);
    }

  }

}
