<?php

final class ReleephBranchCommitFieldSpecification
  extends ReleephFieldSpecification {

  public function getFieldKey() {
    return 'commit';
  }

  public function getName() {
    return pht('Commit');
  }

  public function getRequiredHandlePHIDsForPropertyView() {
    $pull = $this->getReleephRequest();

    if ($pull->getCommitPHID()) {
      return array($pull->getCommitPHID());
    }

    return array();
  }

  public function renderPropertyViewValue(array $handles) {
    return $this->renderHandleList($handles);
  }

}
