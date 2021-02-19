<?php

final class PhabricatorPolicyFilterSet
  extends Phobject {

  private $users = array();
  private $objects = array();

  private $capabilities = array();
  private $queue = array();
  private $results = array();

  public function addCapability(
    PhabricatorUser $user,
    PhabricatorPolicyInterface $object,
    $capability) {

    $user_key = $this->getUserKey($user);
    $this->users[$user_key] = $user;

    $object_key = $this->getObjectKey($object);
    $this->objects[$object_key] = $object;

    if (!isset($this->capabilities[$capability][$user_key][$object_key])) {
      $this->capabilities[$capability][$user_key][$object_key] = true;
      $this->queue[$capability][$user_key][$object_key] = true;
    }

    return $this;
  }

  public function hasCapability(
    PhabricatorUser $user,
    PhabricatorPolicyInterface $object,
    $capability) {

    $user_key = $this->getUserKey($user);
    $this->users[$user_key] = $user;

    $object_key = $this->getObjectKey($object);
    $this->objects[$object_key] = $object;

    if (!isset($this->capabilities[$capability][$user_key][$object_key])) {
      throw new Exception(
        pht(
          'Capability "%s" for user "%s" on object "%s" is being resolved, '.
          'but was never queued with "addCapability()".',
          $capability,
          $user_key,
          $object_key));
    }

    if (!isset($this->results[$capability][$user_key][$object_key])) {
      $this->resolveCapabilities();
    }

    return $this->results[$capability][$user_key][$object_key];
  }

  private function getUserKey(PhabricatorUser $user) {
    return $user->getCacheFragment();
  }

  private function getObjectKey(PhabricatorPolicyInterface $object) {
    $object_phid = $object->getPHID();

    if (!$object_phid) {
      throw new Exception(
        pht(
          'Unable to perform capability tests on an object (of class "%s") '.
          'with no PHID.',
          get_class($object)));
    }

    return $object_phid;
  }

  private function resolveCapabilities() {

    // This class is primarily used to test if a list of users (like
    // subscribers) can see a single object. It is not structured in a way
    // that makes this particularly efficient, and performance would probably
    // be improved if filtering supported this use case more narrowly.

    foreach ($this->queue as $capability => $user_map) {
      foreach ($user_map as $user_key => $object_map) {
        $user = $this->users[$user_key];
        $objects = array_select_keys($this->objects, array_keys($object_map));

        $filter = id(new PhabricatorPolicyFilter())
          ->setViewer($user)
          ->requireCapabilities(array($capability));
        $results = $filter->apply($objects);

        foreach ($object_map as $object_key => $object) {
          $has_capability = (bool)isset($results[$object_key]);
          $this->results[$capability][$user_key][$object_key] = $has_capability;
        }
      }
    }

    $this->queue = array();
  }

  public static function loadHandleViewCapabilities(
    $viewer,
    $handles,
    array $objects) {

    $capabilities = array(
      PhabricatorPolicyCapability::CAN_VIEW,
    );

    assert_instances_of($objects, 'PhabricatorPolicyInterface');

    if (!$objects) {
      return;
    }

    $viewer_map = array();
    foreach ($handles as $handle_key => $handle) {
      if (!$handle->hasCapabilities()) {
        continue;
      }
      $viewer_map[$handle->getPHID()] = $handle_key;
    }

    if (!$viewer_map) {
      return;
    }

    $users = id(new PhabricatorPeopleQuery())
      ->setViewer($viewer)
      ->withPHIDs(array_keys($viewer_map))
      ->execute();
    $users = mpull($users, null, 'getPHID');

    $filter_set = new self();

    foreach ($users as $user_phid => $user) {
      foreach ($objects as $object) {
        foreach ($capabilities as $capability) {
          $filter_set->addCapability($user, $object, $capability);
        }
      }
    }

    foreach ($users as $user_phid => $user) {
      $handle_key = $viewer_map[$user_phid];
      $handle = $handles[$handle_key];
      foreach ($objects as $object) {
        foreach ($capabilities as $capability) {
          $has_capability = $filter_set->hasCapability(
            $user,
            $object,
            $capability);

          $handle->attachCapability(
            $object,
            $capability,
            $has_capability);
        }
      }
    }
  }

}
