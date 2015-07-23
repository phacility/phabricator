<?php

final class PhabricatorObjectQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $phids = array();
  private $names = array();
  private $types;

  private $namedResults;

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withNames(array $names) {
    $this->names = $names;
    return $this;
  }

  public function withTypes(array $types) {
    $this->types = $types;
    return $this;
  }

  protected function loadPage() {
    if ($this->namedResults === null) {
      $this->namedResults = array();
    }

    $types = PhabricatorPHIDType::getAllTypes();
    if ($this->types) {
      $types = array_select_keys($types, $this->types);
    }

    $names = array_unique($this->names);
    $phids = $this->phids;

    // We allow objects to be named by their PHID in addition to their normal
    // name so that, e.g., CLI tools which accept object names can also accept
    // PHIDs and work as users expect.
    $actually_phids = array();
    if ($names) {
      foreach ($names as $key => $name) {
        if (!strncmp($name, 'PHID-', 5)) {
          $actually_phids[] = $name;
          $phids[] = $name;
          unset($names[$key]);
        }
      }
    }

    $phids = array_unique($phids);

    if ($names) {
      $name_results = $this->loadObjectsByName($types, $names);
    } else {
      $name_results = array();
    }

    if ($phids) {
      $phid_results = $this->loadObjectsByPHID($types, $phids);
    } else {
      $phid_results = array();
    }

    foreach ($actually_phids as $phid) {
      if (isset($phid_results[$phid])) {
        $name_results[$phid] = $phid_results[$phid];
      }
    }

    $this->namedResults += $name_results;

    return $phid_results + mpull($name_results, null, 'getPHID');
  }

  public function getNamedResults() {
    if ($this->namedResults === null) {
      throw new PhutilInvalidStateException('execute');
    }
    return $this->namedResults;
  }

  private function loadObjectsByName(array $types, array $names) {
    $groups = array();
    foreach ($names as $name) {
      foreach ($types as $type => $type_impl) {
        if (!$type_impl->canLoadNamedObject($name)) {
          continue;
        }
        $groups[$type][] = $name;
        break;
      }
    }

    $results = array();
    foreach ($groups as $type => $group) {
      $results += $types[$type]->loadNamedObjects($this, $group);
    }

    return $results;
  }

  private function loadObjectsByPHID(array $types, array $phids) {
    $results = array();

    $groups = array();
    foreach ($phids as $phid) {
      $type = phid_get_type($phid);
      $groups[$type][] = $phid;
    }

    $in_flight = $this->getPHIDsInFlight();
    foreach ($groups as $type => $group) {
      // We check the workspace for each group, because some groups may trigger
      // other groups to load (for example, transactions load their objects).
      $workspace = $this->getObjectsFromWorkspace($group);

      foreach ($group as $key => $phid) {
        if (isset($workspace[$phid])) {
          $results[$phid] = $workspace[$phid];
          unset($group[$key]);
        }
      }

      if (!$group) {
        continue;
      }

      // Don't try to load PHIDs which are already "in flight"; this prevents
      // us from recursing indefinitely if policy checks or edges form a loop.
      // We will decline to load the corresponding objects.
      foreach ($group as $key => $phid) {
        if (isset($in_flight[$phid])) {
          unset($group[$key]);
        }
      }

      if ($group && isset($types[$type])) {
        $this->putPHIDsInFlight($group);
        $objects = $types[$type]->loadObjects($this, $group);

        $map = mpull($objects, null, 'getPHID');
        $this->putObjectsInWorkspace($map);
        $results += $map;
      }
    }

    return $results;
  }

  protected function didFilterResults(array $filtered) {
    foreach ($this->namedResults as $name => $result) {
      if (isset($filtered[$result->getPHID()])) {
        unset($this->namedResults[$name]);
      }
    }
  }

  /**
   * This query disables policy filtering if the only required capability is
   * the view capability.
   *
   * The view capability is always checked in the subqueries, so we do not need
   * to re-filter results. For any other set of required capabilities, we do.
   */
  protected function shouldDisablePolicyFiltering() {
    $view_capability = PhabricatorPolicyCapability::CAN_VIEW;
    if ($this->getRequiredCapabilities() === array($view_capability)) {
      return true;
    }
    return false;
  }

  public function getQueryApplicationClass() {
    return null;
  }

}
