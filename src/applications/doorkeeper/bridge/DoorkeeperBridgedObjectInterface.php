<?php

interface DoorkeeperBridgedObjectInterface {

  public function getBridgedObject();
  public function attachBridgedObject(DoorkeeperExternalObject $object = null);

}
