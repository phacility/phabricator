<?php

final class DifferentialRevisionCloseTransaction
  extends DifferentialRevisionActionTransaction {

  const TRANSACTIONTYPE = 'differential.revision.close';
  const ACTIONKEY = 'close';

  protected function getRevisionActionLabel(
    DifferentialRevision $revision,
    PhabricatorUser $viewer) {
    return pht('Close Revision');
  }

  protected function getRevisionActionDescription(
    DifferentialRevision $revision,
    PhabricatorUser $viewer) {
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

    // See T13300. When a revision is closed, we promote it out of "Draft"
    // immediately. This usually happens when a user creates a draft revision
    // and then lands the associated commit before the revision leaves draft.
    $object->setShouldBroadcast(true);
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
    $commit_phid = $this->getMetadataValue('commitPHID');
    if ($commit_phid) {
      $commit = id(new DiffusionCommitQuery())
        ->setViewer($this->getViewer())
        ->withPHIDs(array($commit_phid))
        ->needIdentities(true)
        ->executeOne();
    } else {
      $commit = null;
    }

    if (!$commit) {
      return pht(
        '%s closed this revision.',
        $this->renderAuthor());
    }

    $author_phid = null;
    if ($commit->hasAuthorIdentity()) {
      $identity = $commit->getAuthorIdentity();
      $author_phid = $identity->getIdentityDisplayPHID();
    }

    $committer_phid = null;
    if ($commit->hasCommitterIdentity()) {
      $identity = $commit->getCommitterIdentity();
      $committer_phid = $identity->getIdentityDisplayPHID();
    }

    if (!$author_phid) {
      return pht(
        'Closed by commit %s.',
        $this->renderHandle($commit_phid));
    } else if (!$committer_phid || ($committer_phid === $author_phid)) {
      return pht(
        'Closed by commit %s (authored by %s).',
        $this->renderHandle($commit_phid),
        $this->renderHandle($author_phid));
    } else {
      return pht(
        'Closed by commit %s (authored by %s, committed by %s).',
        $this->renderHandle($commit_phid),
        $this->renderHandle($author_phid),
        $this->renderHandle($committer_phid));
    }
  }

  public function getTitleForFeed() {
    return pht(
      '%s closed %s.',
      $this->renderAuthor(),
      $this->renderObject());
  }

  public function getTransactionTypeForConduit($xaction) {
    return 'close';
  }

  public function getFieldValuesForConduit($object, $data) {
    $commit_phid = $object->getMetadataValue('commitPHID');

    if ($commit_phid) {
      $commit_phids = array($commit_phid);
    } else {
      $commit_phids = array();
    }

    return array(
      'commitPHIDs' => $commit_phids,
    );
  }

}
