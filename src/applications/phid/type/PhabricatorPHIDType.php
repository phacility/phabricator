<?php

abstract class PhabricatorPHIDType extends Phobject {

  final public function getTypeConstant() {
    $const = $this->getPhobjectClassConstant('TYPECONST');

    if (!is_string($const) || !preg_match('/^[A-Z]{4}$/', $const)) {
      throw new Exception(
        pht(
          '%s class "%s" has an invalid %s property. PHID '.
          'constants must be a four character uppercase string.',
          __CLASS__,
          get_class($this),
          'TYPECONST'));
    }

    return $const;
  }

  abstract public function getTypeName();

  public function newObject() {
    return null;
  }

  public function getTypeIcon() {
    // Default to the application icon if the type doesn't specify one.
    $application_class = $this->getPHIDTypeApplicationClass();
    if ($application_class) {
      $application = newv($application_class, array());
      return $application->getFontIcon();
    }

    return null;
  }


  /**
   * Get the class name for the application this type belongs to.
   *
   * @return string|null Class name of the corresponding application, or null
   *   if the type is not bound to an application.
   */
  public function getPHIDTypeApplicationClass() {
    // TODO: Some day this should probably be abstract, but for now it only
    // affects global search and there's no real burning need to go classify
    // every PHID type.
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
    throw new PhutilMethodNotImplementedException();
  }


  /**
   * Get all known PHID types.
   *
   * To get PHID types a given user has access to, see
   * @{method:getAllInstalledTypes}.
   *
   * @return dict<string, PhabricatorPHIDType> Map of type constants to types.
   */
  final public static function getAllTypes() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getTypeConstant')
      ->execute();
  }


  /**
   * Get all PHID types of applications installed for a given viewer.
   *
   * @param PhabricatorUser Viewing user.
   * @return dict<string, PhabricatorPHIDType> Map of constants to installed
   *  types.
   */
  public static function getAllInstalledTypes(PhabricatorUser $viewer) {
    $all_types = self::getAllTypes();

    $installed_types = array();

    $app_classes = array();
    foreach ($all_types as $key => $type) {
      $app_class = $type->getPHIDTypeApplicationClass();

      if ($app_class === null) {
        // If the PHID type isn't bound to an application, include it as
        // installed.
        $installed_types[$key] = $type;
        continue;
      }

      // Otherwise, we need to check if this application is installed before
      // including the PHID type.
      $app_classes[$app_class][$key] = $type;
    }

    if ($app_classes) {
      $apps = id(new PhabricatorApplicationQuery())
        ->setViewer($viewer)
        ->withInstalled(true)
        ->withClasses(array_keys($app_classes))
        ->execute();

      foreach ($apps as $app_class => $app) {
        $installed_types += $app_classes[$app_class];
      }
    }

    return $installed_types;
  }

}
