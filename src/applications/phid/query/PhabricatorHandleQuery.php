<?php

final class PhabricatorHandleQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $objectCapabilities;
  private $phids = array();

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function requireObjectCapabilities(array $capabilities) {
    $this->objectCapabilities = $capabilities;
    return $this;
  }

  protected function getRequiredObjectCapabilities() {
    if ($this->objectCapabilities) {
      return $this->objectCapabilities;
    }
    return $this->getRequiredCapabilities();
  }

  protected function loadPage() {
    $types = PhabricatorPHIDType::getAllTypes();

    $phids = array_unique($this->phids);
    if (!$phids) {
      return array();
    }

    $object_query = id(new PhabricatorObjectQuery())
      ->withPHIDs($phids)
      ->setParentQuery($this)
      ->requireCapabilities($this->getRequiredObjectCapabilities())
      ->setViewer($this->getViewer());

    // We never want the subquery to raise policy exceptions, even if this
    // query is being executed via executeOne(). Policy exceptions are not
    // meaningful or relevant for handles, which load in an "Unknown" or
    // "Restricted" state after encountering a policy violation.
    $object_query->setRaisePolicyExceptions(false);

    $objects = $object_query->execute();
    $filtered = $object_query->getPolicyFilteredPHIDs();

    $groups = array();
    foreach ($phids as $phid) {
      $type = phid_get_type($phid);
      $groups[$type][] = $phid;
    }

    $results = array();
    foreach ($groups as $type => $phid_group) {
      $handles = array();
      foreach ($phid_group as $key => $phid) {
        if (isset($handles[$phid])) {
          unset($phid_group[$key]);
          // The input had a duplicate PHID; just skip it.
          continue;
        }
        $handles[$phid] = id(new PhabricatorObjectHandle())
          ->setType($type)
          ->setPHID($phid);
        if (isset($objects[$phid])) {
          $handles[$phid]->setComplete(true);
        } else if (isset($filtered[$phid])) {
          $handles[$phid]->setPolicyFiltered(true);
        }
      }

      if (isset($types[$type])) {
        $type_objects = array_select_keys($objects, $phid_group);
        if ($type_objects) {
          $have_object_phids = array_keys($type_objects);
          $types[$type]->loadHandles(
            $this,
            array_select_keys($handles, $have_object_phids),
            $type_objects);
        }
      }

      $results += $handles;
    }

    return $results;
  }

  public function getQueryApplicationClass() {
    return null;
  }

}
