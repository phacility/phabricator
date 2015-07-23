<?php

final class ReleephOriginalCommitFieldSpecification
  extends ReleephFieldSpecification {

  public function getFieldKey() {
    return 'commit:name';
  }

  public function getName() {
    return pht('Commit');
  }

  public function getRequiredHandlePHIDsForPropertyView() {
    return array(
      $this->getReleephRequest()->getRequestCommitPHID(),
    );
  }


  public function renderPropertyViewValue(array $handles) {
    return $this->renderHandleList($handles);
  }

}
