<?php

abstract class AlmanacQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  protected function didFilterPage(array $objects) {
    if (head($objects) instanceof AlmanacPropertyInterface) {
      // NOTE: We load properties for obsolete historical reasons. It may make
      // sense to re-examine that assumption shortly.

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
        $object_properties = mpull($object_properties, null, 'getFieldName');

        // Create synthetic properties for defaults on the object itself.
        $specs = $object->getAlmanacPropertyFieldSpecifications();
        foreach ($specs as $key => $spec) {
          if (empty($object_properties[$key])) {
            $object_properties[$key] = id(new AlmanacProperty())
              ->setObjectPHID($object->getPHID())
              ->setFieldName($key)
              ->setFieldValue($spec->getValueForTransaction());
          }
        }

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
