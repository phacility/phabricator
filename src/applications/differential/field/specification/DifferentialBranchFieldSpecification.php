<?php

final class DifferentialBranchFieldSpecification
  extends DifferentialFieldSpecification {

  public function shouldAppearOnRevisionView() {
    return true;
  }

  public function renderLabelForRevisionView() {
    return 'Branch:';
  }

  public function renderValueForRevisionView() {
    $diff = $this->getManualDiff();

    $branch = $diff->getBranch();
    $bookmark = $diff->getBookmark();
    $has_branch = ($branch != '');
    $has_bookmark = ($bookmark != '');
    if ($has_branch && $has_bookmark) {
      $branch = "{$bookmark} bookmark on {$branch} branch";
    } else if ($has_bookmark) {
      $branch = "{$bookmark} bookmark";
    } else if (!$has_branch) {
      return null;
    }

    return $branch;
  }

  public function renderValueForMail($phase) {
    $status = $this->getRevision()->getStatus();

    if ($status != ArcanistDifferentialRevisionStatus::NEEDS_REVISION &&
        $status != ArcanistDifferentialRevisionStatus::ACCEPTED) {
      return null;
    }

    $diff = $this->getRevision()->loadActiveDiff();
    if ($diff) {
      $branch = $diff->getBranch();
      if ($branch) {
        return "BRANCH\n  $branch";
      }
    }
  }

  public function didWriteRevision(DifferentialRevisionEditor $editor) {
    $branch = $this->getDiff()->getBranch();
    $match = null;
    if (preg_match('/^T(\d+)/i', $branch, $match)) { // No $ to allow T123_demo.
      list(, $task_id) = $match;
      $task = id(new ManiphestTask())->load($task_id);
      if ($task) {
        id(new PhabricatorEdgeEditor())
          ->setActor($this->getUser())
          ->addEdge(
            $this->getRevision()->getPHID(),
            PhabricatorEdgeConfig::TYPE_DREV_HAS_RELATED_TASK,
            $task->getPHID())
          ->save();
      }
    }
  }

  public function getCommitMessageTips() {
    return array(
      'Name branch "T123" to attach the diff to a task.',
    );
  }

}
