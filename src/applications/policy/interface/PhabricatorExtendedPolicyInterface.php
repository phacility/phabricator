<?php

/**
 * Allows an object to define a more complex policy than it can with
 * @{interface:PhabricatorPolicyInterface} alone.
 *
 * Some objects have complex policies which depend on the policies of other
 * objects. For example, users can generally only see a Revision in
 * Differential if they can also see the Repository it belongs to.
 *
 * These policies are normally enforced implicitly in the Query layer, by
 * discarding objects which have related objects that can not be loaded. In
 * most cases this has the same effect as really applying these policy checks
 * would.
 *
 * However, in some cases an object's policies are later checked by a different
 * viewer. For example, before we execute Herald rules, we check that the rule
 * owners can see the object we are about to evaluate.
 *
 * In these cases, we need to account for these complex policies. We could do
 * this by reloading the object over and over again for each viewer, but this
 * implies a large performance cost. Instead, extended policies make it
 * efficient to check policies against an object for multiple viewers.
 */
interface PhabricatorExtendedPolicyInterface {

  /**
   * Get the extended policy for an object.
   *
   * Return a list of additional policy checks that the viewer must satisfy
   * in order to have the specified capability. This allows you to encode rules
   * like "to see a revision, the viewer must also be able to see the repository
   * it belongs to".
   *
   * For example, to specify that the viewer must be able to see some other
   * object in order to see this one, you could return:
   *
   *   return array(
   *     array($other_object, PhabricatorPolicyCapability::CAN_VIEW),
   *     // ...
   *   );
   *
   * If you don't have the actual object you want to check, you can return its
   * PHID instead:
   *
   *   return array(
   *     array($other_phid, PhabricatorPolicyCapability::CAN_VIEW),
   *     // ...
   *   );
   *
   * You can return a list of capabilities instead of a single capability if
   * you want to require multiple capabilities on a single object:
   *
   *   return array(
   *     array(
   *       $other_object,
   *       array(
   *         PhabricatorPolicyCapability::CAN_VIEW,
   *         PhabricatorPolicyCapability::CAN_EDIT,
   *       ),
   *     ),
   *     // ...
   *   );
   *
   * @param const Capability being tested.
   * @param PhabricatorUser Viewer whose capabilities are being tested.
   * @return list<pair<wild, wild>> List of extended policies.
   */
  public function getExtendedPolicy($capability, PhabricatorUser $viewer);

}

// TEMPLATE IMPLEMENTATION /////////////////////////////////////////////////////

/* -(  PhabricatorExtendedPolicyInterface  )--------------------------------- */
/*

  public function getExtendedPolicy($capability, PhabricatorUser $viewer) {
    $extended = array();
    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        // ...
        break;
    }
    return $extended;
  }

*/
