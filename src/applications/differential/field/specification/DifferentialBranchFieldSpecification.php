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

    return phutil_escape_html($branch);
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

}
