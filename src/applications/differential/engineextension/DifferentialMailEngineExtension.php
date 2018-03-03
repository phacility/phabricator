<?php

final class DifferentialMailEngineExtension
  extends PhabricatorMailEngineExtension {

  const EXTENSIONKEY = 'differential';

  public function supportsObject($object) {
    return ($object instanceof DifferentialRevision);
  }

  public function newMailStampTemplates($object) {
    return array(
      id(new PhabricatorPHIDMailStamp())
        ->setKey('author')
        ->setLabel(pht('Author')),
      id(new PhabricatorPHIDMailStamp())
        ->setKey('reviewer')
        ->setLabel(pht('Reviewer')),
      id(new PhabricatorPHIDMailStamp())
        ->setKey('blocking-reviewer')
        ->setLabel(pht('Reviewer')),
      id(new PhabricatorPHIDMailStamp())
        ->setKey('resigned-reviewer')
        ->setLabel(pht('Reviewer')),
      id(new PhabricatorPHIDMailStamp())
        ->setKey('revision-repository')
        ->setLabel(pht('Revision Repository')),
      id(new PhabricatorPHIDMailStamp())
        ->setKey('revision-status')
        ->setLabel(pht('Revision Status')),
    );
  }

  public function newMailStamps($object, array $xactions) {
    $editor = $this->getEditor();
    $viewer = $this->getViewer();

    $revision = id(new DifferentialRevisionQuery())
      ->setViewer($viewer)
      ->needReviewers(true)
      ->withPHIDs(array($object->getPHID()))
      ->executeOne();

    $reviewers = array();
    $blocking = array();
    $resigned = array();
    foreach ($revision->getReviewers() as $reviewer) {
      $reviewer_phid = $reviewer->getReviewerPHID();

      if ($reviewer->isResigned()) {
        $resigned[] = $reviewer_phid;
      } else {
        $reviewers[] = $reviewer_phid;
        if ($reviewer->isBlocking()) {
          $blocking[] = $reviewer_phid;
        }
      }
    }

    $this->getMailStamp('author')
      ->setValue($revision->getAuthorPHID());

    $this->getMailStamp('reviewer')
      ->setValue($reviewers);

    $this->getMailStamp('blocking-reviewer')
      ->setValue($blocking);

    $this->getMailStamp('resigned-reviewer')
      ->setValue($resigned);

    $this->getMailStamp('revision-repository')
      ->setValue($revision->getRepositoryPHID());

    $this->getMailStamp('revision-status')
      ->setValue($revision->getModernRevisionStatus());
  }

}
