<?php

final class DifferentialDateCreatedFieldSpecification
  extends DifferentialFieldSpecification {

  public function shouldAppearOnRevisionList() {
    return true;
  }

  public function renderHeaderForRevisionList() {
    return 'Created';
  }

  public function renderValueForRevisionList(DifferentialRevision $revision) {
    return phabricator_date($revision->getDateCreated(), $this->getUser());
  }

}
