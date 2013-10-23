<?php

abstract class PhabricatorPHIDType {

  abstract public function getTypeConstant();
  abstract public function getTypeName();

  public function newObject() {
    return null;
  }

  /**
   * Build a @{class:PhabricatorPolicyAwareQuery} to load objects of this type
   * by PHID.
   *
   * If you can not build a single query which satisfies this requirement, you
   * can provide a dummy implementation for this method and overload
   * @{method:loadObjects} instead.
   *
   * @param PhabricatorObjectQuery Query being executed.
   * @param list<phid> PHIDs to load.
   * @return PhabricatorPolicyAwareQuery Query object which loads the
   *   specified PHIDs when executed.
   */
  abstract protected function buildQueryForObjects(
    PhabricatorObjectQuery $query,
    array $phids);


  /**
   * Load objects of this type, by PHID. For most PHID types, it is only
   * necessary to implement @{method:buildQueryForObjects} to get object
   * loading to work.
   *
   * @param PhabricatorObjectQuery Query being executed.
   * @param list<phid> PHIDs to load.
   * @return list<wild> Corresponding objects.
   */
  public function loadObjects(
    PhabricatorObjectQuery $query,
    array $phids) {

    $object_query = $this->buildQueryForObjects($query, $phids)
      ->setViewer($query->getViewer())
      ->setParentQuery($query);

    // If the user doesn't have permission to use the application at all,
    // just mark all the PHIDs as filtered. This primarily makes these
    // objects show up as "Restricted" instead of "Unknown" when loaded as
    // handles, which is technically true.
    if (!$object_query->canViewerUseQueryApplication()) {
      $object_query->addPolicyFilteredPHIDs(array_fuse($phids));
      return array();
    }

    return $object_query->execute();
  }


  /**
   * Populate provided handles with application-specific data, like titles and
   * URIs.
   *
   * NOTE: The `$handles` and `$objects` lists are guaranteed to be nonempty
   * and have the same keys: subclasses are expected to load information only
   * for handles with visible objects.
   *
   * Because of this guarantee, a safe implementation will typically look like*
   *
   *   foreach ($handles as $phid => $handle) {
   *     $object = $objects[$phid];
   *
   *     $handle->setStuff($object->getStuff());
   *     // ...
   *   }
   *
   * In general, an implementation should call `setName()` and `setURI()` on
   * each handle at a minimum. See @{class:PhabricatorObjectHandle} for other
   * handle properties.
   *
   * @param PhabricatorHandleQuery          Issuing query object.
   * @param list<PhabricatorObjectHandle>   Handles to populate with data.
   * @param list<Object>                    Objects for these PHIDs loaded by
   *                                        @{method:buildQueryForObjects()}.
   * @return void
   */
  abstract public function loadHandles(
    PhabricatorHandleQuery $query,
    array $handles,
    array $objects);

  public function canLoadNamedObject($name) {
    return false;
  }

  public function loadNamedObjects(
    PhabricatorObjectQuery $query,
    array $names) {
    throw new Exception("Not implemented!");
  }

  public static function getAllTypes() {
    static $types;
    if ($types === null) {
      $objects = id(new PhutilSymbolLoader())
        ->setAncestorClass(__CLASS__)
        ->loadObjects();

      $map = array();
      $original = array();
      foreach ($objects as $object) {
        $type = $object->getTypeConstant();
        if (isset($map[$type])) {
          $that_class = $original[$type];
          $this_class = get_class($object);
          throw new Exception(
            "Two PhabricatorPHIDType classes ({$that_class}, {$this_class}) ".
            "both handle PHID type '{$type}'. A type may be handled by only ".
            "one class.");
        }

        $original[$type] = get_class($object);
        $map[$type] = $object;
      }

      $types = $map;
    }

    return $types;
  }

}
