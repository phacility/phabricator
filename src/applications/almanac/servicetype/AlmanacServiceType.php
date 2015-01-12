<?php

abstract class AlmanacServiceType extends Phobject {


  /**
   * Return a very short human-readable name for this service type, like
   * "Custom".
   *
   * @return string Very short human-readable service type name.
   */
  abstract public function getServiceTypeShortName();

  /**
   * Return a short, human-readable name for this service type, like
   * "Custom Service".
   *
   * @return string Human-readable name for this service type.
   */
  abstract public function getServiceTypeName();


  /**
   * Return a brief summary of this service type.
   *
   * This summary should be a sentence or two long.
   *
   * @return string Brief, human-readable description of this service type.
   */
  abstract public function getServiceTypeDescription();


  public function getServiceTypeIcon() {
    return 'fa-cog';
  }

  /**
   * Return `true` if this service type is a Phabricator cluster service type.
   *
   * These special services change the behavior of Phabricator, and require
   * elevated permission to create.
   *
   * @return bool True if this is a Phabricator cluster service type.
   */
  public function isClusterServiceType() {
    return false;
  }


  public function getDefaultPropertyMap() {
    return array();
  }

  public function getFieldSpecifications() {
    return array();
  }

  public function getStatusMessages(AlmanacService $service) {
    return array();
  }

  /**
   * List all available service type implementations.
   *
   * @return map<string, object> Dictionary of available service types.
   */
  public static function getAllServiceTypes() {
    $types = id(new PhutilSymbolLoader())
      ->setAncestorClass(__CLASS__)
      ->loadObjects();

    return msort($types, 'getServiceTypeName');
  }


}
