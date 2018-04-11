<?php

abstract class AlmanacQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $needProperties;

  public function needProperties($need_properties) {
    $this->needProperties = $need_properties;
    return $this;
  }

  protected function getNeedProperties() {
    return $this->needProperties;
  }

  protected function didFilterPage(array $objects) {
    $has_properties = (head($objects) instanceof AlmanacPropertyInterface);

    if ($has_properties && $this->needProperties) {
      $property_query = id(new AlmanacPropertyQuery())
        ->setViewer($this->getViewer())
        ->setParentQuery($this)
        ->withObjects($objects);

      $properties = $property_query->execute();

      $properties = mgroup($properties, 'getObjectPHID');
      foreach ($objects as $object) {
        $object_properties = idx($properties, $object->getPHID(), array());
        $object_properties = mpull($object_properties, null, 'getFieldName');

        // Create synthetic properties for defaults on the object itself.
        $specs = $object->getAlmanacPropertyFieldSpecifications();
        foreach ($specs as $key => $spec) {
          if (empty($object_properties[$key])) {
            $default_value = $spec->getValueForTransaction();

            $object_properties[$key] = id(new AlmanacProperty())
              ->setObjectPHID($object->getPHID())
              ->setFieldName($key)
              ->setFieldValue($default_value);
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
