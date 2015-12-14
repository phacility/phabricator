<?php

interface PhabricatorConduitResultInterface
  extends PhabricatorPHIDInterface {

  public function getFieldSpecificationsForConduit();
  public function getFieldValuesForConduit();
  public function getConduitSearchAttachments();

}

// TEMPLATE IMPLEMENTATION /////////////////////////////////////////////////////

/* -(  PhabricatorConduitResultInterface  )---------------------------------- */
/*

  public function getFieldSpecificationsForConduit() {
    return array(
      id(new PhabricatorConduitSearchFieldSpecification())
        ->setKey('name')
        ->setType('string')
        ->setDescription(pht('The name of the object.')),
    );
  }

  public function getFieldValuesForConduit() {
    return array(
      'name' => $this->getName(),
    );
  }

  public function getConduitSearchAttachments() {
    return array();
  }

*/
