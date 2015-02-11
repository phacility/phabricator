<?php

abstract class AlmanacQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  protected function didFilterPage(array $objects) {
    if (head($objects) instanceof AlmanacPropertyInterface) {
      // NOTE: We load properties unconditionally because CustomField assumes
      // it can always generate a list of fields on an object. It may make
      // sense to re-examine that assumption eventually.

      $property_query = id(new AlmanacPropertyQuery())
        ->setViewer($this->getViewer())
        ->setParentQuery($this)
        ->withObjectPHIDs(mpull($objects, 'getPHID'));

      // NOTE: We disable policy filtering and object attachment to avoid
      // a cyclic dependency where objects need their properties and properties
      // need their objects. We'll attach the objects below, and have already
      // implicitly checked the necessary policies.
      $property_query->setDisablePolicyFilteringAndAttachment(true);

      $properties = $property_query->execute();

      $properties = mgroup($properties, 'getObjectPHID');
      foreach ($objects as $object) {
        $object_properties = idx($properties, $object->getPHID(), array());
        foreach ($object_properties as $property) {
          $property->attachObject($object);
        }
        $object->attachAlmanacProperties($object_properties);
      }
    }

    return $objects;
  }

  public function getQueryApplicationClass() {
    return 'PhabricatorAlmanacApplication';
  }

}
