<?php

final class PhabricatorObjectListQuery {

  private $viewer;
  private $objectList;
  private $allowedTypes = array();
  private $allowPartialResults;

  public function setAllowPartialResults($allow_partial_results) {
    $this->allowPartialResults = $allow_partial_results;
    return $this;
  }

  public function getAllowPartialResults() {
    return $this->allowPartialResults;
  }

  public function setAllowedTypes(array $allowed_types) {
    $this->allowedTypes = $allowed_types;
    return $this;
  }

  public function getAllowedTypes() {
    return $this->allowedTypes;
  }

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public function getViewer() {
    return $this->viewer;
  }

  public function setObjectList($object_list) {
    $this->objectList = $object_list;
    return $this;
  }

  public function getObjectList() {
    return $this->objectList;
  }

  public function execute() {
    $names = $this->getObjectList();
    $names = array_unique(array_filter(preg_split('/[\s,]+/', $names)));

    $objects = $this->loadObjects($names);

    $types = array();
    foreach ($objects as $name => $object) {
      $types[phid_get_type($object->getPHID())][] = $name;
    }

    $invalid = array();
    if ($this->getAllowedTypes()) {
      $allowed = array_fuse($this->getAllowedTypes());
      foreach ($types as $type => $names_of_type) {
        if (empty($allowed[$type])) {
          $invalid[] = $names_of_type;
        }
      }
    }
    $invalid = array_mergev($invalid);

    $missing = array();
    foreach ($names as $name) {
      if (empty($objects[$name])) {
        $missing[] = $name;
      }
    }

    // NOTE: We could couple this less tightly with Differential, but it is
    // currently the only thing that uses it, and we'd have to add a lot of
    // extra API to loosen this. It's not clear that this will be useful
    // elsewhere any time soon, so let's cross that bridge when we come to it.

    if (!$this->getAllowPartialResults()) {
      if ($invalid && $missing) {
        throw new DifferentialFieldParseException(
          pht(
            'The objects you have listed include objects of the wrong '.
            'type (%s) and objects which do not exist (%s).',
            implode(', ', $invalid),
            implode(', ', $missing)));
      } else if ($invalid) {
        throw new DifferentialFieldParseException(
          pht(
            'The objects you have listed include objects of the wrong '.
            'type (%s).',
            implode(', ', $invalid)));
      } else if ($missing) {
        throw new DifferentialFieldParseException(
          pht(
            'The objects you have listed include objects which do not '.
            'exist (%s).',
            implode(', ', $missing)));
      }
    }

    return array_values(array_unique(mpull($objects, 'getPHID')));
  }

  private function loadObjects($names) {
    // First, try to load visible objects using monograms. This covers most
    // object types, but does not cover users or user email addresses.
    $query = id(new PhabricatorObjectQuery())
      ->setViewer($this->getViewer())
      ->withNames($names);

    $query->execute();
    $objects = $query->getNamedResults();

    $results = array();
    foreach ($names as $key => $name) {
      if (isset($objects[$name])) {
        $results[$name] = $objects[$name];
        unset($names[$key]);
      }
    }

    if ($names) {
      // We still have some symbols we haven't been able to resolve, so try to
      // load users. Try by username first...
      $users = id(new PhabricatorPeopleQuery())
        ->setViewer($this->getViewer())
        ->withUsernames($names)
        ->execute();

      $user_map = array();
      foreach ($users as $user) {
        $user_map[phutil_utf8_strtolower($user->getUsername())] = $user;
      }

      foreach ($names as $key => $name) {
        $normal_name = phutil_utf8_strtolower($name);
        if (isset($user_map[$normal_name])) {
          $results[$name] = $user_map[$normal_name];
          unset($names[$key]);
        }
      }
    }

    return $results;
  }


}
