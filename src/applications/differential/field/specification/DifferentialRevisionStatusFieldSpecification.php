<?php

final class DifferentialRevisionStatusFieldSpecification
  extends DifferentialFieldSpecification {

  public function shouldAppearOnRevisionView() {
      return false;
  }

  public function shouldAppearOnRevisionList() {
    return true;
  }

  public function renderHeaderForRevisionList() {
    return 'Status';
  }

  public function renderValueForRevisionList(DifferentialRevision $revision) {
    return ArcanistDifferentialRevisionStatus::getNameForRevisionStatus(
      $revision->getStatus());
  }

}
