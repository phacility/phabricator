<?php

final class PolicyLockOptionType
  extends PhabricatorConfigJSONOptionType {

  public function validateOption(PhabricatorConfigOption $option, $value) {
    $capabilities = id(new PhutilSymbolLoader())
      ->setAncestorClass('PhabricatorPolicyCapability')
      ->loadObjects();
    $capabilities = mpull($capabilities, null, 'getCapabilityKey');

    $policy_phids = array();
    foreach ($value as $capability_key => $policy) {
      $capability = idx($capabilities, $capability_key);
      if (!$capability) {
        throw new Exception(
          pht(
            'Capability "%s" does not exist.',
            $capability_key));
      }
      if (phid_get_type($policy) !=
          PhabricatorPHIDConstants::PHID_TYPE_UNKNOWN) {
        $policy_phids[$policy] = $policy;
      } else {
        try {
          $policy_object = PhabricatorPolicyQuery::getGlobalPolicy($policy);
        // this exception is not helpful here as its about global policy;
        // throw a better exception
        } catch (Exception $ex) {
          throw new Exception(
            pht(
              'Capability "%s" has invalid policy "%s".',
              $capability_key,
              $policy));
        }
      }

      if ($policy == PhabricatorPolicies::POLICY_PUBLIC) {
        if (!$capability->shouldAllowPublicPolicySetting()) {
          throw new Exception(
            pht(
              'Capability "%s" does not support public policy.',
              $capability_key));
        }
      }
    }

    if ($policy_phids) {
      $handles = id(new PhabricatorHandleQuery())
        ->setViewer(PhabricatorUser::getOmnipotentUser())
        ->withPhids($policy_phids)
        ->execute();
      $handles = mpull($handles, null, 'getPHID');
      foreach ($value as $capability_key => $policy) {
        $handle = $handles[$policy];
        if (!$handle->isComplete()) {
          throw new Exception(
            pht(
              'Capability "%s" has invalid policy "%s"; "%s" does not exist.',
              $capability_key,
              $policy,
              $policy));
        }
      }
    }
  }

}
