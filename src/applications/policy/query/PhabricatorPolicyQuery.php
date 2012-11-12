<?php

final class PhabricatorPolicyQuery extends PhabricatorQuery {

  private $viewer;
  private $object;

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public function setObject(PhabricatorPolicyInterface $object) {
    $this->object = $object;
    return $this;
  }

  public static function renderPolicyDescriptions(
    PhabricatorUser $viewer,
    PhabricatorPolicyInterface $object) {

    $results = array();
    $policies = null;
    $global = self::getGlobalPolicies();

    $capabilities = $object->getCapabilities();
    foreach ($capabilities as $capability) {
      $policy = $object->getPolicy($capability);
      if (!$policy) {
        continue;
      }

      if (isset($global[$policy])) {
        $results[$capability] = $global[$policy]->renderDescription();
        continue;
      }

      if ($policies === null) {
        // This slightly overfetches data, but it shouldn't generally
        // be a problem.
        $policies = id(new PhabricatorPolicyQuery())
          ->setViewer($viewer)
          ->setObject($object)
          ->execute();
      }

      $results[$capability] = $policies[$policy]->renderDescription();
    }

    return $results;
  }

  public function execute() {
    if (!$this->viewer) {
      throw new Exception('Call setViewer() before execute()!');
    }
    if (!$this->object) {
      throw new Exception('Call setObject() before execute()!');
    }

    $results = $this->getGlobalPolicies();

    if ($this->viewer->getPHID()) {
      $projects = id(new PhabricatorProjectQuery())
        ->setViewer($this->viewer)
        ->withMemberPHIDs(array($this->viewer->getPHID()))
        ->execute();
      if ($projects) {
        foreach ($projects as $project) {
          $results[] = id(new PhabricatorPolicy())
            ->setType(PhabricatorPolicyType::TYPE_PROJECT)
            ->setPHID($project->getPHID())
            ->setHref('/project/view/'.$project->getID().'/')
            ->setName($project->getName());
        }
      }
    }

    $results = mpull($results, null, 'getPHID');

    $other_policies = array();
    $capabilities = $this->object->getCapabilities();
    foreach ($capabilities as $capability) {
      $policy = $this->object->getPolicy($capability);
      if (!$policy) {
        continue;
      }
      $other_policies[$policy] = $policy;
    }

    // If this install doesn't have "Public" enabled, remove it as an option
    // unless the object already has a "Public" policy. In this case we retain
    // the policy but enforce it as thought it was "All Users".
    $show_public = PhabricatorEnv::getEnvConfig('policy.allow-public');
    if (!$show_public &&
        empty($other_policies[PhabricatorPolicies::POLICY_PUBLIC])) {
      unset($results[PhabricatorPolicies::POLICY_PUBLIC]);
    }

    $other_policies = array_diff_key($other_policies, $results);

    if ($other_policies) {
      $handles = id(new PhabricatorObjectHandleData($other_policies))
        ->setViewer($this->viewer)
        ->loadHandles();
      foreach ($other_policies as $phid) {
        $handle = $handles[$phid];
        $results[$phid] = id(new PhabricatorPolicy())
          ->setType(PhabricatorPolicyType::TYPE_MASKED)
          ->setPHID($handle->getPHID())
          ->setHref($handle->getLink())
          ->setName($handle->getFullName());
      }
    }

    $results = msort($results, 'getSortKey');

    return $results;
  }

  private static function getGlobalPolicies() {
    static $constants = array(
      PhabricatorPolicies::POLICY_PUBLIC,
      PhabricatorPolicies::POLICY_USER,
      PhabricatorPolicies::POLICY_ADMIN,
      PhabricatorPolicies::POLICY_NOONE,
    );

    $results = array();
    foreach ($constants as $constant) {
      $results[$constant] = id(new PhabricatorPolicy())
        ->setType(PhabricatorPolicyType::TYPE_GLOBAL)
        ->setPHID($constant)
        ->setName(self::getGlobalPolicyName($constant));
    }

    return $results;
  }

  private static function getGlobalPolicyName($policy) {
    switch ($policy) {
      case PhabricatorPolicies::POLICY_PUBLIC:
        return pht('Public (No Login Required)');
      case PhabricatorPolicies::POLICY_USER:
        return pht('All Users');
      case PhabricatorPolicies::POLICY_ADMIN:
        return pht('Administrators');
      case PhabricatorPolicies::POLICY_NOONE:
        return pht('No One');
      default:
        return pht('Unknown Policy');
    }
  }

}

