<?php

abstract class PhabricatorRepositoryCommitMessageParserWorker
  extends PhabricatorRepositoryCommitParserWorker {

  protected function getImportStepFlag() {
    return PhabricatorRepositoryCommit::IMPORTED_MESSAGE;
  }

  abstract protected function getFollowupTaskClass();

  final protected function parseCommit(
    PhabricatorRepository $repository,
    PhabricatorRepositoryCommit $commit) {

    if (!$this->shouldSkipImportStep()) {
      $viewer = $this->getViewer();

      $ref = $commit->newCommitRef($viewer);

      $data = $this->loadCommitData($commit);
      $data->setCommitRef($ref);

      $this->updateCommitData($commit, $data);
    }

    $this->queueCommitTask($this->getFollowupTaskClass());
  }

  final protected function updateCommitData(
    PhabricatorRepositoryCommit $commit,
    PhabricatorRepositoryCommitData $data) {

    $ref = $data->getCommitRef();
    $viewer = $this->getViewer();

    $author = $ref->getAuthor();
    $committer = $ref->getCommitter();
    $has_committer = $committer !== null && (bool)strlen($committer);

    $identity_engine = id(new DiffusionRepositoryIdentityEngine())
      ->setViewer($viewer)
      ->setSourcePHID($commit->getPHID());

    // See T13538. It is possible to synthetically construct a Git commit with
    // no author and arrive here with NULL for the author value.

    // This is distinct from a commit with an empty author. Because both these
    // cases are degenerate and we can't resolve NULL into an identity, cast
    // NULL to the empty string and merge the flows.
    $author = phutil_string_cast($author);

    $author_identity = $identity_engine->newResolvedIdentity($author);

    if ($has_committer) {
      $committer_identity = $identity_engine->newResolvedIdentity($committer);
    } else {
      $committer_identity = null;
    }

    $data->setAuthorName(id(new PhutilUTF8StringTruncator())
      ->setMaximumBytes(255)
      ->truncateString((string)$author));

    $data->setCommitDetail('authorEpoch', $ref->getAuthorEpoch());
    $data->setCommitDetail('authorName', $ref->getAuthorName());
    $data->setCommitDetail('authorEmail', $ref->getAuthorEmail());

    $data->setCommitDetail(
      'authorIdentityPHID', $author_identity->getPHID());
    $data->setCommitDetail(
      'authorPHID',
      $author_identity->getCurrentEffectiveUserPHID());

    // See T13538. It is possible to synthetically construct a Git commit with
    // no message. As above, treat this as though it is the same as the empty
    // message.
    $message = $ref->getMessage();
    $message = phutil_string_cast($message);
    $data->setCommitMessage($message);

    if ($has_committer) {
      $data->setCommitDetail('committer', $committer);

      $data->setCommitDetail('committerName', $ref->getCommitterName());
      $data->setCommitDetail('committerEmail', $ref->getCommitterEmail());

      $data->setCommitDetail(
        'committerPHID',
        $committer_identity->getCurrentEffectiveUserPHID());

      $data->setCommitDetail(
        'committerIdentityPHID', $committer_identity->getPHID());

      $commit->setCommitterIdentityPHID($committer_identity->getPHID());
    }

    $repository = $this->repository;

    $author_phid = $data->getCommitDetail('authorPHID');
    $committer_phid = $data->getCommitDetail('committerPHID');

    if ($author_phid != $commit->getAuthorPHID()) {
      $commit->setAuthorPHID($author_phid);
    }

    $commit->setAuthorIdentityPHID($author_identity->getPHID());

    $commit->setSummary($data->getSummary());

    $commit->save();
    $data->save();

    $commit->writeImportStatusFlag(
      PhabricatorRepositoryCommit::IMPORTED_MESSAGE);
  }

}
