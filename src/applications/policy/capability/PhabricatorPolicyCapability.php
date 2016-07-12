<?php

abstract class PhabricatorPolicyCapability extends Phobject {

  const CAN_VIEW        = 'view';
  const CAN_EDIT        = 'edit';
  const CAN_JOIN        = 'join';

  /**
   * Get the unique key identifying this capability. This key must be globally
   * unique. Application capabilities should be namespaced. For example:
   *
   *   application.create
   *
   * @return string Globally unique capability key.
   */
  final public function getCapabilityKey() {
    return $this->getPhobjectClassConstant('CAPABILITY');
  }


  /**
   * Return a human-readable descriptive name for this capability, like
   * "Can View".
   *
   * @return string Human-readable name describing the capability.
   */
  abstract public function getCapabilityName();


  /**
   * Return a human-readable string describing what not having this capability
   * prevents the user from doing. For example:
   *
   *   - You do not have permission to edit this object.
   *   - You do not have permission to create new tasks.
   *
   * @return string Human-readable name describing what failing a check for this
   *   capability prevents the user from doing.
   */
  public function describeCapabilityRejection() {
    return null;
  }

  /**
   * Can this capability be set to "public"? Broadly, this is only appropriate
   * for view and view-related policies.
   *
   * @return bool True to allow the "public" policy. Returns false by default.
   */
  public function shouldAllowPublicPolicySetting() {
    return false;
  }

  final public static function getCapabilityByKey($key) {
    return idx(self::getCapabilityMap(), $key);
  }

  final public static function getCapabilityMap() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getCapabilityKey')
      ->execute();
  }

}
