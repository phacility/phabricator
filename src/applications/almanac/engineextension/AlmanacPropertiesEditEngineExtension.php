<?php

final class AlmanacPropertiesEditEngineExtension
  extends PhabricatorEditEngineExtension {

  const EXTENSIONKEY = 'almanac.properties';

  public function isExtensionEnabled() {
    return true;
  }

  public function getExtensionName() {
    return pht('Almanac Properties');
  }

  public function supportsObject(
    PhabricatorEditEngine $engine,
    PhabricatorApplicationTransactionInterface $object) {
    return ($object instanceof AlmanacPropertyInterface);
  }

  public function buildCustomEditFields(
    PhabricatorEditEngine $engine,
    PhabricatorApplicationTransactionInterface $object) {

    return array(
      id(new AlmanacSetPropertyEditField())
        ->setKey('property.set')
        ->setTransactionType($object->getAlmanacPropertySetTransactionType())
        ->setConduitDescription(
          pht('Pass a map of values to set one or more properties.'))
        ->setConduitTypeDescription(pht('Map of property names to values.'))
        ->setIsFormField(false),
      id(new AlmanacDeletePropertyEditField())
        ->setKey('property.delete')
        ->setTransactionType($object->getAlmanacPropertyDeleteTransactionType())
        ->setConduitDescription(
          pht('Pass a list of property names to delete properties.'))
        ->setConduitTypeDescription(pht('List of property names.'))
        ->setIsFormField(false),
    );
  }

}
