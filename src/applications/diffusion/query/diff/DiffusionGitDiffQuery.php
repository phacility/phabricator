<?php

final class DiffusionGitDiffQuery extends DiffusionDiffQuery {

  protected function executeQuery() {
    $drequest = $this->getRequest();
    $repository = $drequest->getRepository();

    $effective_commit = $this->getEffectiveCommit();
    if (!$effective_commit) {
      return null;
    }
    // TODO: This side effect is kind of sketchy.
    $drequest->setCommit($effective_commit);

    $raw_query = DiffusionRawDiffQuery::newFromDiffusionRequest($drequest);
    $raw_diff = $raw_query->loadRawDiff();

    if (!$raw_diff) {
      return null;
    }

    $parser = new ArcanistDiffParser();

    $try_encoding = $repository->getDetail('encoding');
    if ($try_encoding) {
      $parser->setTryEncoding($try_encoding);
    }

    $parser->setDetectBinaryFiles(true);
    $changes = $parser->parseDiff($raw_diff);

    $diff = DifferentialDiff::newFromRawChanges($changes);
    $changesets = $diff->getChangesets();
    $changeset = reset($changesets);

    $this->renderingReference = $drequest->generateURI(
      array(
        'action' => 'rendering-ref',
      ));

    return $changeset;
  }

}
