<?php

final class DiffusionRepositoryMetricsSearchEngineAttachment
  extends PhabricatorSearchEngineAttachment {

  public function getAttachmentName() {
    return pht('Repository Metrics');
  }

  public function getAttachmentDescription() {
    return pht(
      'Get metrics (like commit count and most recent commit) for each '.
      'repository.');
  }

  public function willLoadAttachmentData($query, $spec) {
    $query
      ->needCommitCounts(true)
      ->needMostRecentCommits(true);
  }

  public function getAttachmentForObject($object, $data, $spec) {
    $commit = $object->getMostRecentCommit();
    if ($commit !== null) {
      $recent_commit = $commit->getFieldValuesForConduit();
    } else {
      $recent_commit = null;
    }

    $commit_count = $object->getCommitCount();
    if ($commit_count !== null) {
      $commit_count = (int)$commit_count;
    }

    return array(
      'commitCount' => $commit_count,
      'recentCommit' => $recent_commit,
    );
  }

}
