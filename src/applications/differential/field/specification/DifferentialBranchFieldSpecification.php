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

}
