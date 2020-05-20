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
      $viewer = PhabricatorUser::getOmnipotentUser();

      $refs_raw = DiffusionQuery::callConduitWithDiffusionRequest(
        $viewer,
        DiffusionRequest::newFromDictionary(
          array(
            'repository' => $repository,
            'user' => $viewer,
          )),
        'diffusion.querycommits',
        array(
          'repositoryPHID' => $repository->getPHID(),
          'phids' => array($commit->getPHID()),
          'bypassCache' => true,
          'needMessages' => true,
        ));

      if (empty($refs_raw['data'])) {
        throw new Exception(
          pht(
            'Unable to retrieve details for commit "%s"!',
            $commit->getPHID()));
      }

      $ref = DiffusionCommitRef::newFromConduitResult(head($refs_raw['data']));
      $this->updateCommitData($ref);
    }

    if ($this->shouldQueueFollowupTasks()) {
      $this->queueTask(
        $this->getFollowupTaskClass(),
        array(
          'commitID' => $commit->getID(),
        ),
        array(
          // We queue followup tasks at default priority so that the queue
          // finishes work it has started before starting more work. If
          // followups are queued at the same priority level, we do all
          // message parses first, then all change parses, etc. This makes
          // progress uneven. See T11677 for discussion.
          'priority' => PhabricatorWorker::PRIORITY_DEFAULT,
        ));
    }
  }

  final protected function updateCommitData(DiffusionCommitRef $ref) {
    $commit = $this->commit;
    $author = $ref->getAuthor();
    $committer = $ref->getCommitter();
    $hashes = $ref->getHashes();
    $has_committer = (bool)strlen($committer);

    $viewer = PhabricatorUser::getOmnipotentUser();

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

    $data = id(new PhabricatorRepositoryCommitData())->loadOneWhere(
      'commitID = %d',
      $commit->getID());
    if (!$data) {
      $data = new PhabricatorRepositoryCommitData();
    }
    $data->setCommitID($commit->getID());
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

    $user = new PhabricatorUser();
    if ($author_phid) {
      $user = $user->loadOneWhere(
        'phid = %s',
        $author_phid);
    }

    if ($author_phid != $commit->getAuthorPHID()) {
      $commit->setAuthorPHID($author_phid);
    }

    $commit->setAuthorIdentityPHID($author_identity->getPHID());

    $commit->setSummary($data->getSummary());
    $commit->save();

    // If we're publishing this commit, we're going to queue tasks to update
    // referenced objects (like tasks and revisions). Otherwise, record some
    // details about why we are not publishing it yet.

    $publisher = $repository->newPublisher();
    if ($publisher->shouldPublishCommit($commit)) {
      $actor = PhabricatorUser::getOmnipotentUser();
      $this->closeRevisions($actor, $ref, $commit, $data);
      $this->closeTasks($actor, $ref, $commit, $data);
    } else {
      $hold_reasons = $publisher->getCommitHoldReasons($commit);
      $data->setCommitDetail('holdReasons', $hold_reasons);
    }

    $data->save();

    $commit->writeImportStatusFlag(
      PhabricatorRepositoryCommit::IMPORTED_MESSAGE);
  }

  private function closeRevisions(
    PhabricatorUser $actor,
    DiffusionCommitRef $ref,
    PhabricatorRepositoryCommit $commit,
    PhabricatorRepositoryCommitData $data) {

    $differential = 'PhabricatorDifferentialApplication';
    if (!PhabricatorApplication::isClassInstalled($differential)) {
      return;
    }

    $repository = $commit->getRepository();

    $field_query = id(new DiffusionLowLevelCommitFieldsQuery())
      ->setRepository($repository)
      ->withCommitRef($ref);

    $field_values = $field_query->execute();

    $revision_id = idx($field_values, 'revisionID');
    if (!$revision_id) {
      return;
    }

    $revision = id(new DifferentialRevisionQuery())
      ->setViewer($actor)
      ->withIDs(array($revision_id))
      ->executeOne();
    if (!$revision) {
      return;
    }

    // NOTE: This is very old code from when revisions had a single reviewer.
    // It still powers the "Reviewer (Deprecated)" field in Herald, but should
    // be removed.
    if (!empty($field_values['reviewedByPHIDs'])) {
      $data->setCommitDetail(
        'reviewerPHID',
        head($field_values['reviewedByPHIDs']));
    }

    $match_data = $field_query->getRevisionMatchData();

    $data->setCommitDetail('differential.revisionID', $revision_id);
    $data->setCommitDetail('revisionMatchData', $match_data);

    $properties = array(
      'revisionMatchData' => $match_data,
    );
    $this->queueObjectUpdate($commit, $revision, $properties);
  }

  private function closeTasks(
    PhabricatorUser $actor,
    DiffusionCommitRef $ref,
    PhabricatorRepositoryCommit $commit,
    PhabricatorRepositoryCommitData $data) {

    $maniphest = 'PhabricatorManiphestApplication';
    if (!PhabricatorApplication::isClassInstalled($maniphest)) {
      return;
    }

    $prefixes = ManiphestTaskStatus::getStatusPrefixMap();
    $suffixes = ManiphestTaskStatus::getStatusSuffixMap();
    $message = $data->getCommitMessage();

    $matches = id(new ManiphestCustomFieldStatusParser())
      ->parseCorpus($message);

    $task_map = array();
    foreach ($matches as $match) {
      $prefix = phutil_utf8_strtolower($match['prefix']);
      $suffix = phutil_utf8_strtolower($match['suffix']);

      $status = idx($suffixes, $suffix);
      if (!$status) {
        $status = idx($prefixes, $prefix);
      }

      foreach ($match['monograms'] as $task_monogram) {
        $task_id = (int)trim($task_monogram, 'tT');
        $task_map[$task_id] = $status;
      }
    }

    if (!$task_map) {
      return;
    }

    $tasks = id(new ManiphestTaskQuery())
      ->setViewer($actor)
      ->withIDs(array_keys($task_map))
      ->execute();
    foreach ($tasks as $task_id => $task) {
      $status = $task_map[$task_id];

      $properties = array(
        'status' => $status,
      );

      $this->queueObjectUpdate($commit, $task, $properties);
    }
  }

  private function queueObjectUpdate(
    PhabricatorRepositoryCommit $commit,
    $object,
    array $properties) {

    $this->queueTask(
      'DiffusionUpdateObjectAfterCommitWorker',
      array(
        'commitPHID' => $commit->getPHID(),
        'objectPHID' => $object->getPHID(),
        'properties' => $properties,
      ),
      array(
        'priority' => PhabricatorWorker::PRIORITY_DEFAULT,
      ));
  }

}
