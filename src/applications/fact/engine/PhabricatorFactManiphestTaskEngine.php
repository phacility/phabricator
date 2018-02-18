<?php

final class PhabricatorFactManiphestTaskEngine
  extends PhabricatorFactEngine {

  public function newFacts() {
    return array(
      id(new PhabricatorPointsFact())
        ->setKey('tasks.count.open'),
    );
  }

  public function supportsDatapointsForObject(PhabricatorLiskDAO $object) {
    return ($object instanceof ManiphestTask);
  }

  public function newDatapointsForObject(PhabricatorLiskDAO $object) {
    $datapoints = array();

    $phid = $object->getPHID();
    $type = phid_get_type($phid);

    $datapoint = $this->getFact('tasks.count.open')
      ->newDatapoint();

    $datapoints[] = $datapoint
      ->setObjectPHID($phid)
      ->setValue(1)
      ->setEpoch($object->getDateCreated());

    return $datapoints;
  }

}
