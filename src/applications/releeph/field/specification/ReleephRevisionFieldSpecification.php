<?php

final class ReleephRevisionFieldSpecification
  extends ReleephFieldSpecification {

  public function getFieldKey() {
    return 'revision';
  }

  public function getName() {
    return 'Revision';
  }

  public function getRequiredHandlePHIDsForPropertyView() {
    $phids = array();

    $phid = $this->getReleephRequest()->loadRequestCommitDiffPHID();
    if ($phid) {
      $phids[] = $phid;
    }

    return $phids;
  }

  public function renderPropertyViewValue(array $handles) {
    return $this->renderHandleList($handles);
  }

}
