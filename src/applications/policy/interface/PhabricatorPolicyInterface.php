<?php

interface PhabricatorPolicyInterface extends PhabricatorPHIDInterface {

  public function getCapabilities();
  public function getPolicy($capability);
  public function hasAutomaticCapability($capability, PhabricatorUser $viewer);

  /**
   * Describe exceptions to an object's policy setting.
   *
   * The intent of this method is to explain and inform users about special
   * cases which override configured policy settings. If this object has any
   * such exceptions, explain them by returning one or more human-readable
   * strings which describe the exception in a broad, categorical way. For
   * example:
   *
   *   - "The owner of an X can always view and edit it."
   *   - "Members of a Y can always view it."
   *
   * You can return `null`, a single string, or a list of strings.
   *
   * The relevant capability to explain (like "view") is passed as a parameter.
   * You should tailor any messages to be relevant to that capability, although
   * they do not need to exclusively describe the capability, and in some cases
   * being more general ("The author can view and edit...") will be more clear.
   *
   * Messages should describe general rules, not specific objects, because the
   * main goal is to teach the user the rules. For example, write "the author",
   * not the specific author's name.
   *
   * @param const @{class:PhabricatorPolicyCapability} constant.
   * @return wild Description of policy exceptions. See above.
   */
  public function describeAutomaticCapability($capability);

}

// TEMPLATE IMPLEMENTATION /////////////////////////////////////////////////////

/* -(  PhabricatorPolicyInterface  )----------------------------------------- */
/*

  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
    );
  }

  public function getPolicy($capability) {
    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        return PhabricatorPolicies::POLICY_USER;
    }
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return false;
  }

  public function describeAutomaticCapability($capability) {
    return null;
  }

*/
