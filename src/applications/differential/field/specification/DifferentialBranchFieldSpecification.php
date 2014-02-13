<?php

final class DifferentialBranchFieldSpecification
  extends DifferentialFieldSpecification {

  public function shouldAppearOnRevisionView() {
    return true;
  }

  public function renderLabelForRevisionView() {
    return 'Branch:';
  }

  private function getBranchOrBookmarkDescription(DifferentialDiff $diff) {
    $branch = $diff->getBranch();
    $bookmark = $diff->getBookmark();
    $has_branch = ($branch != '');
    $has_bookmark = ($bookmark != '');
    if ($has_branch && $has_bookmark) {
      return "{$bookmark} bookmark on {$branch} branch";
    } else if ($has_bookmark) {
      return "{$bookmark} bookmark";
    } else if ($has_branch) {
      return $branch;
    }
    return null;
  }

  public function renderValueForRevisionView() {
    $diff = $this->getManualDiff();
    return $this->getBranchOrBookmarkDescription($diff);
  }

  public function renderValueForMail($phase) {
    $diff = $this->getRevision()->loadActiveDiff();
    if ($diff) {
      $description = $this->getBranchOrBookmarkDescription($diff);
      if ($description) {
        return "BRANCH\n  {$description}";
      }
    }

    return null;
  }

  public function didWriteRevision(DifferentialRevisionEditor $editor) {
    $maniphest = 'PhabricatorApplicationManiphest';
    if (!PhabricatorApplication::isClassInstalled($maniphest)) {
      return;
    }

    $branch = $this->getDiff()->getBranch();
    $match = null;
    if (preg_match('/^T(\d+)/i', $branch, $match)) { // No $ to allow T123_demo.
      list(, $task_id) = $match;
      $task = id(new ManiphestTaskQuery())
        ->setViewer($editor->requireActor())
        ->withIDs(array($task_id))
        ->executeOne();
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
