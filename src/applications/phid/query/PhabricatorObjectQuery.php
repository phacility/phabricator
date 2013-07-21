<?php

final class PhabricatorObjectQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $phids;
  private $names;

  private $namedResults;

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  public function withNames(array $names) {
    $this->names = $names;
    return $this;
  }

  public function loadPage() {
    $types = PhabricatorPHIDType::getAllTypes();

    $this->namedResults = $this->loadObjectsByName($types);

    return $this->loadObjectsByPHID($types) +
           mpull($this->namedResults, null, 'getPHID');
  }

  public function getNamedResults() {
    if ($this->namedResults === null) {
      throw new Exception("Call execute() before getNamedResults()!");
    }
    return $this->namedResults;
  }

  private function loadObjectsByName(array $types) {
    $names = $this->names;
    if (!$names) {
      return array();
    }

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

  private function loadObjectsByPHID(array $types) {
    $phids = $this->phids;
    if (!$phids) {
      return array();
    }

    $groups = array();
    foreach ($phids as $phid) {
      $type = phid_get_type($phid);
      $groups[$type][] = $phid;
    }

    $results = array();
    foreach ($groups as $type => $group) {
      if (isset($types[$type])) {
        $objects = $types[$type]->loadObjects($this, $group);
        $results += mpull($objects, null, 'getPHID');
      }
    }

    return $results;
  }

}
