<?php

final class DifferentialRevisionCloseTransaction
  extends DifferentialRevisionActionTransaction {

  const TRANSACTIONTYPE = 'differential.revision.close';
  const ACTIONKEY = 'close';

  protected function getRevisionActionLabel() {
    return pht('Close Revision');
  }

  protected function getRevisionActionDescription(
    DifferentialRevision $revision) {
    return pht('This revision will be closed.');
  }

  public function getIcon() {
    return 'fa-check';
  }

  public function getColor() {
    return 'indigo';
  }

  protected function getRevisionActionOrder() {
    return 300;
  }

  public function getActionName() {
    return pht('Closed');
  }

  public function generateOldValue($object) {
    return $object->isClosed();
  }

  public function applyInternalEffects($object, $value) {
    $was_accepted = $object->isAccepted();

    $status_published = DifferentialRevisionStatus::PUBLISHED;
    $object->setModernRevisionStatus($status_published);

    $object->setProperty(
      DifferentialRevision::PROPERTY_CLOSED_FROM_ACCEPTED,
      $was_accepted);
  }

  protected function validateAction($object, PhabricatorUser $viewer) {
    if ($this->hasEditor()) {
      if ($this->getEditor()->getIsCloseByCommit()) {
        // If we're closing a revision because we discovered a commit, we don't
        // care what state it was in.
        return;
      }
    }

    if ($object->isClosed()) {
      throw new Exception(
        pht(
          'You can not close this revision because it has already been '.
          'closed. Only open revisions can be closed.'));
    }

    if (!$object->isAccepted()) {
      throw new Exception(
        pht(
          'You can not close this revision because it has not been accepted. '.
          'Revisions must be accepted before they can be closed.'));
    }

    $config_key = 'differential.always-allow-close';
    if (!PhabricatorEnv::getEnvConfig($config_key)) {
      if (!$this->isViewerRevisionAuthor($object, $viewer)) {
        throw new Exception(
          pht(
            'You can not close this revision because you are not the '.
            'author. You can only close revisions you own. You can change '.
            'this behavior by adjusting the "%s" setting in Config.',
            $config_key));
      }
    }
  }

  public function getTitle() {
    if (!$this->getMetadataValue('isCommitClose')) {
      return pht(
        '%s closed this revision.',
        $this->renderAuthor());
    }

    $commit_phid = $this->getMetadataValue('commitPHID');
    $committer_phid = $this->getMetadataValue('committerPHID');
    $author_phid = $this->getMetadataValue('authorPHID');

    if ($committer_phid) {
      $committer_name = $this->renderHandle($committer_phid);
    } else {
      $committer_name = $this->getMetadataValue('committerName');
    }

    if ($author_phid) {
      $author_name = $this->renderHandle($author_phid);
    } else {
      $author_name = $this->getMetadatavalue('authorName');
    }

    $same_phid =
      strlen($committer_phid) &&
      strlen($author_phid) &&
      ($committer_phid == $author_phid);

    $same_name =
      !strlen($committer_phid) &&
      !strlen($author_phid) &&
      ($committer_name == $author_name);

    if ($same_name || $same_phid) {
      return pht(
        'Closed by commit %s (authored by %s).',
        $this->renderHandle($commit_phid),
        $author_name);
    } else {
      return pht(
        'Closed by commit %s (authored by %s, committed by %s).',
        $this->renderHandle($commit_phid),
        $author_name,
        $committer_name);
    }
  }

  public function getTitleForFeed() {
    return pht(
      '%s closed %s.',
      $this->renderAuthor(),
      $this->renderObject());
  }

}
