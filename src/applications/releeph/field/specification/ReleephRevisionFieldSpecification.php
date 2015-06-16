<?php

final class ReleephRevisionFieldSpecification
  extends ReleephFieldSpecification {

  public function getFieldKey() {
    return 'revision';
  }

  public function getName() {
    return pht('Revision');
  }

  public function getRequiredHandlePHIDsForPropertyView() {
    $requested_object = $this->getObject()->getRequestedObjectPHID();
    if (!($requested_object instanceof DifferentialRevision)) {
      return array();
    }

    return array(
      $requested_object->getPHID(),
    );
  }

  public function renderPropertyViewValue(array $handles) {
    return $this->renderHandleList($handles);
  }

}
