<?php

final class DifferentialDateModifiedFieldSpecification
  extends DifferentialFieldSpecification {

  public function shouldAppearOnRevisionList() {
    return true;
  }

  public function renderHeaderForRevisionList() {
    return 'Updated';
  }

  public function renderValueForRevisionList(DifferentialRevision $revision) {
    return phabricator_datetime($revision->getDateModified(), $this->getUser());
  }

}
