<?php

final class DifferentialBranchField
  extends DifferentialCustomField {

  public function getFieldKey() {
    return 'differential:branch';
  }

  public function getFieldName() {
    return pht('Branch');
  }

  public function getFieldDescription() {
    return pht('Shows the branch a diff came from.');
  }

  public function shouldAppearInPropertyView() {
    return true;
  }

  public function renderPropertyViewLabel() {
    return $this->getFieldName();
  }

  public function renderPropertyViewValue(array $handles) {
    return $this->getBranchDescription($this->getObject()->getActiveDiff());
  }

  private function getBranchDescription(DifferentialDiff $diff) {
    $branch = $diff->getBranch();
    $bookmark = $diff->getBookmark();

    if (strlen($branch) && strlen($bookmark)) {
      return pht('%s (bookmark) on %s (branch)', $bookmark, $branch);
    } else if (strlen($bookmark)) {
      return pht('%s (bookmark)', $bookmark);
    } else if (strlen($branch)) {
      return $branch;
    } else {
      return null;
    }
  }

}
