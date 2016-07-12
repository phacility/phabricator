<?php

abstract class AlmanacPropertyController extends AlmanacController {

  private $propertyObject;

  public function getPropertyObject() {
    return $this->propertyObject;
  }

  protected function loadPropertyObject() {
    $viewer = $this->getViewer();
    $request = $this->getRequest();
    $object_phid = $request->getStr('objectPHID');


    switch (phid_get_type($object_phid)) {
      case AlmanacBindingPHIDType::TYPECONST:
        $query = new AlmanacBindingQuery();
        break;
      case AlmanacDevicePHIDType::TYPECONST:
        $query = new AlmanacDeviceQuery();
        break;
      case AlmanacServicePHIDType::TYPECONST:
        $query = new AlmanacServiceQuery();
        break;
      default:
        return new Aphront404Response();
    }

    $object = $query
      ->setViewer($viewer)
      ->withPHIDs(array($object_phid))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->needProperties(true)
      ->executeOne();

    if (!$object) {
      return new Aphront404Response();
    }

    if (!($object instanceof AlmanacPropertyInterface)) {
      return new Aphront404Response();
    }

    $this->propertyObject = $object;

    return null;
  }


}
