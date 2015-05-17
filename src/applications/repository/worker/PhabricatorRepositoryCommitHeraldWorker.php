<?php

final class PhabricatorRepositoryCommitHeraldWorker
  extends PhabricatorRepositoryCommitParserWorker {

  public function getRequiredLeaseTime() {
    // Herald rules may take a long time to process.
    return phutil_units('4 hours in seconds');
  }

  protected function parseCommit(
    PhabricatorRepository $repository,
    PhabricatorRepositoryCommit $commit) {

    // Reload the commit to pull commit data and audit requests.
    $commit = id(new DiffusionCommitQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withIDs(array($commit->getID()))
      ->needCommitData(true)
      ->needAuditRequests(true)
      ->executeOne();
    $data = $commit->getCommitData();

    if (!$data) {
      throw new PhabricatorWorkerPermanentFailureException(
        pht(
          'Unable to load commit data. The data for this task is invalid '.
          'or no longer exists.'));
    }

    $commit->attachRepository($repository);

    $content_source = PhabricatorContentSource::newForSource(
      PhabricatorContentSource::SOURCE_DAEMON,
      array());

    $committer_phid = $data->getCommitDetail('committerPHID');
    $author_phid = $data->getCommitDetail('authorPHID');
    $acting_as_phid = nonempty(
      $committer_phid,
      $author_phid,
      id(new PhabricatorDiffusionApplication())->getPHID());

    $editor = id(new PhabricatorAuditEditor())
      ->setActor(PhabricatorUser::getOmnipotentUser())
      ->setActingAsPHID($acting_as_phid)
      ->setContinueOnMissingFields(true)
      ->setContinueOnNoEffect(true)
      ->setContentSource($content_source);

    $xactions = array();
    $xactions[] = id(new PhabricatorAuditTransaction())
      ->setTransactionType(PhabricatorAuditTransaction::TYPE_COMMIT)
      ->setDateCreated($commit->getEpoch())
      ->setNewValue(array(
        'description'   => $data->getCommitMessage(),
        'summary'       => $data->getSummary(),
        'authorName'    => $data->getAuthorName(),
        'authorPHID'    => $commit->getAuthorPHID(),
        'committerName' => $data->getCommitDetail('committer'),
        'committerPHID' => $data->getCommitDetail('committerPHID'),
      ));

    $reverts_refs = id(new DifferentialCustomFieldRevertsParser())
      ->parseCorpus($data->getCommitMessage());
    $reverts = array_mergev(ipull($reverts_refs, 'monograms'));

    if ($reverts) {
      $reverted_commits = id(new DiffusionCommitQuery())
        ->setViewer(PhabricatorUser::getOmnipotentUser())
        ->withRepository($repository)
        ->withIdentifiers($reverts)
        ->execute();
      $reverted_commit_phids = mpull($reverted_commits, 'getPHID', 'getPHID');

      // NOTE: Skip any write attempts if a user cleverly implies a commit
      // reverts itself.
      unset($reverted_commit_phids[$commit->getPHID()]);

      $reverts_edge = DiffusionCommitRevertsCommitEdgeType::EDGECONST;
      $xactions[] = id(new PhabricatorAuditTransaction())
        ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
        ->setMetadataValue('edge:type', $reverts_edge)
        ->setNewValue(array('+' => array_fuse($reverted_commit_phids)));
    }

    try {
      $raw_patch = $this->loadRawPatchText($repository, $commit);
    } catch (Exception $ex) {
      $raw_patch = pht('Unable to generate patch: %s', $ex->getMessage());
    }
    $editor->setRawPatch($raw_patch);

    return $editor->applyTransactions($commit, $xactions);
  }

  private function loadRawPatchText(
    PhabricatorRepository $repository,
    PhabricatorRepositoryCommit $commit) {

    $drequest = DiffusionRequest::newFromDictionary(
      array(
        'user' => PhabricatorUser::getOmnipotentUser(),
        'repository' => $repository,
        'commit' => $commit->getCommitIdentifier(),
      ));

    $raw_query = DiffusionRawDiffQuery::newFromDiffusionRequest($drequest);
    $raw_query->setLinesOfContext(3);

    $time_key = 'metamta.diffusion.time-limit';
    $byte_key = 'metamta.diffusion.byte-limit';
    $time_limit = PhabricatorEnv::getEnvConfig($time_key);
    $byte_limit = PhabricatorEnv::getEnvConfig($byte_key);

    if ($time_limit) {
      $raw_query->setTimeout($time_limit);
    }

    $raw_diff = $raw_query->loadRawDiff();

    $size = strlen($raw_diff);
    if ($byte_limit && $size > $byte_limit) {
      $pretty_size = phutil_format_bytes($size);
      $pretty_limit = phutil_format_bytes($byte_limit);
      throw new Exception(pht(
        'Patch size of %s exceeds configured byte size limit (%s) of %s.',
        $pretty_size,
        $byte_key,
        $pretty_limit));
    }

    return $raw_diff;
  }

}
