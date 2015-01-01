<?php

final class PhabricatorEdgeConfig extends PhabricatorEdgeConstants {

  const TABLE_NAME_EDGE       = 'edge';
  const TABLE_NAME_EDGEDATA   = 'edgedata';

  const TYPE_OBJECT_HAS_SUBSCRIBER      = 21;
  const TYPE_SUBSCRIBED_TO_OBJECT       = 22;

  const TYPE_OBJECT_HAS_UNSUBSCRIBER    = 23;
  const TYPE_UNSUBSCRIBED_FROM_OBJECT   = 24;

  const TYPE_OBJECT_HAS_FILE            = 25;
  const TYPE_FILE_HAS_OBJECT            = 26;

  const TYPE_OBJECT_HAS_CONTRIBUTOR     = 33;
  const TYPE_CONTRIBUTED_TO_OBJECT      = 34;

  const TYPE_OBJECT_USES_CREDENTIAL     = 39;
  const TYPE_CREDENTIAL_USED_BY_OBJECT  = 40;

  const TYPE_DASHBOARD_HAS_PANEL        = 45;
  const TYPE_PANEL_HAS_DASHBOARD        = 46;

  const TYPE_OBJECT_HAS_WATCHER         = 47;
  const TYPE_WATCHER_HAS_OBJECT         = 48;

/* !!!! STOP !!!! STOP !!!! STOP !!!! STOP !!!! STOP !!!! STOP !!!! STOP !!!! */

  // HEY! DO NOT ADD NEW CONSTANTS HERE!
  // Instead, subclass PhabricatorEdgeType.

/* !!!! STOP !!!! STOP !!!! STOP !!!! STOP !!!! STOP !!!! STOP !!!! STOP !!!! */

  const TYPE_TEST_NO_CYCLE              = 9000;

  const TYPE_PHOB_HAS_ASANATASK         = 80001;
  const TYPE_ASANATASK_HAS_PHOB         = 80000;

  const TYPE_PHOB_HAS_ASANASUBTASK      = 80003;
  const TYPE_ASANASUBTASK_HAS_PHOB      = 80002;

  const TYPE_PHOB_HAS_JIRAISSUE         = 80004;
  const TYPE_JIRAISSUE_HAS_PHOB         = 80005;


  /**
   * Build @{class:PhabricatorLegacyEdgeType} objects for edges which have not
   * yet been modernized. This allows code to act as though we've completed
   * the edge type migration before we actually do all the work, by building
   * these fake type objects.
   *
   * @param list<const> List of edge types that objects should not be built for.
   *   This is used to avoid constructing duplicate objects for edge constants
   *   which have migrated and already have a real object.
   * @return list<PhabricatorLegacyEdgeType> Real-looking edge type objects for
   *   unmigrated edge types.
   */
  public static function getLegacyTypes(array $exclude) {
    $consts = array_merge(
      range(1, 50),
      array(9000),
      range(80000, 80005));

    $exclude[] = 15; // Was TYPE_COMMIT_HAS_PROJECT
    $exclude[] = 16; // Was TYPE_PROJECT_HAS_COMMIT

    $exclude[] = 27; // Was TYPE_ACCOUNT_HAS_MEMBER
    $exclude[] = 28; // Was TYPE_MEMBER_HAS_ACCOUNT

    $exclude[] = 43; // Was TYPE_OBJECT_HAS_COLUMN
    $exclude[] = 44; // Was TYPE_COLUMN_HAS_OBJECT

    $consts = array_diff($consts, $exclude);

    $map = array();
    foreach ($consts as $const) {
      $prevent_cycles = self::shouldPreventCycles($const);
      $inverse_constant = self::getInverse($const);

      $map[$const] = id(new PhabricatorLegacyEdgeType())
        ->setEdgeConstant($const)
        ->setShouldPreventCycles($prevent_cycles)
        ->setInverseEdgeConstant($inverse_constant)
        ->setStrings(
          array(
            self::getAddStringForEdgeType($const),
            self::getRemoveStringForEdgeType($const),
            self::getEditStringForEdgeType($const),
            self::getFeedStringForEdgeType($const),
          ));
    }

    return $map;
  }

  private static function getInverse($edge_type) {
    static $map = array(
      self::TYPE_OBJECT_HAS_SUBSCRIBER => self::TYPE_SUBSCRIBED_TO_OBJECT,
      self::TYPE_SUBSCRIBED_TO_OBJECT => self::TYPE_OBJECT_HAS_SUBSCRIBER,

      self::TYPE_OBJECT_HAS_UNSUBSCRIBER => self::TYPE_UNSUBSCRIBED_FROM_OBJECT,
      self::TYPE_UNSUBSCRIBED_FROM_OBJECT => self::TYPE_OBJECT_HAS_UNSUBSCRIBER,

      self::TYPE_OBJECT_HAS_FILE => self::TYPE_FILE_HAS_OBJECT,
      self::TYPE_FILE_HAS_OBJECT => self::TYPE_OBJECT_HAS_FILE,

      self::TYPE_OBJECT_HAS_CONTRIBUTOR => self::TYPE_CONTRIBUTED_TO_OBJECT,
      self::TYPE_CONTRIBUTED_TO_OBJECT => self::TYPE_OBJECT_HAS_CONTRIBUTOR,

      self::TYPE_PHOB_HAS_ASANATASK => self::TYPE_ASANATASK_HAS_PHOB,
      self::TYPE_ASANATASK_HAS_PHOB => self::TYPE_PHOB_HAS_ASANATASK,

      self::TYPE_PHOB_HAS_ASANASUBTASK => self::TYPE_ASANASUBTASK_HAS_PHOB,
      self::TYPE_ASANASUBTASK_HAS_PHOB => self::TYPE_PHOB_HAS_ASANASUBTASK,

      self::TYPE_PHOB_HAS_JIRAISSUE => self::TYPE_JIRAISSUE_HAS_PHOB,
      self::TYPE_JIRAISSUE_HAS_PHOB => self::TYPE_PHOB_HAS_JIRAISSUE,

      self::TYPE_OBJECT_USES_CREDENTIAL => self::TYPE_CREDENTIAL_USED_BY_OBJECT,
      self::TYPE_CREDENTIAL_USED_BY_OBJECT => self::TYPE_OBJECT_USES_CREDENTIAL,

      self::TYPE_PANEL_HAS_DASHBOARD => self::TYPE_DASHBOARD_HAS_PANEL,
      self::TYPE_DASHBOARD_HAS_PANEL => self::TYPE_PANEL_HAS_DASHBOARD,

      self::TYPE_OBJECT_HAS_WATCHER => self::TYPE_WATCHER_HAS_OBJECT,
      self::TYPE_WATCHER_HAS_OBJECT => self::TYPE_OBJECT_HAS_WATCHER,
    );

    return idx($map, $edge_type);
  }

  private static function shouldPreventCycles($edge_type) {
    static $map = array(
      self::TYPE_TEST_NO_CYCLE => true,
    );
    return isset($map[$edge_type]);
  }

  public static function establishConnection($phid_type, $conn_type) {
    $map = PhabricatorPHIDType::getAllTypes();
    if (isset($map[$phid_type])) {
      $type = $map[$phid_type];
      $object = $type->newObject();
      if ($object) {
        return $object->establishConnection($conn_type);
      }
    }

    static $class_map = array(
      PhabricatorPHIDConstants::PHID_TYPE_TOBJ  => 'HarbormasterObject',
      PhabricatorPHIDConstants::PHID_TYPE_XOBJ  => 'DoorkeeperExternalObject',
    );

    $class = idx($class_map, $phid_type);

    if (!$class) {
      throw new Exception(
        "Edges are not available for objects of type '{$phid_type}'!");
    }

    return newv($class, array())->establishConnection($conn_type);
  }

  public static function getEditStringForEdgeType($type) {
    switch ($type) {
      case self::TYPE_OBJECT_HAS_SUBSCRIBER:
        return '%s edited subscriber(s), added %d: %s; removed %d: %s.';
      case self::TYPE_SUBSCRIBED_TO_OBJECT:
      case self::TYPE_UNSUBSCRIBED_FROM_OBJECT:
      case self::TYPE_FILE_HAS_OBJECT:
      case self::TYPE_CONTRIBUTED_TO_OBJECT:
        return '%s edited object(s), added %d: %s; removed %d: %s.';
      case self::TYPE_OBJECT_HAS_UNSUBSCRIBER:
        return '%s edited unsubcriber(s), added %d: %s; removed %d: %s.';
      case self::TYPE_OBJECT_HAS_FILE:
        return '%s edited file(s), added %d: %s; removed %d: %s.';
      case self::TYPE_OBJECT_HAS_CONTRIBUTOR:
        return '%s edited contributor(s), added %d: %s; removed %d: %s.';
      case self::TYPE_DASHBOARD_HAS_PANEL:
        return '%s edited panel(s), added %d: %s; removed %d: %s.';
      case self::TYPE_PANEL_HAS_DASHBOARD:
        return '%s edited dashboard(s), added %d: %s; removed %d: %s.';
      case self::TYPE_SUBSCRIBED_TO_OBJECT:
      case self::TYPE_UNSUBSCRIBED_FROM_OBJECT:
      case self::TYPE_FILE_HAS_OBJECT:
      case self::TYPE_CONTRIBUTED_TO_OBJECT:
      default:
        return '%s edited object(s), added %d: %s; removed %d: %s.';

    }
  }

  public static function getAddStringForEdgeType($type) {
    switch ($type) {
      case self::TYPE_OBJECT_HAS_SUBSCRIBER:
        return '%s added %d subscriber(s): %s.';
      case self::TYPE_OBJECT_HAS_UNSUBSCRIBER:
        return '%s added %d unsubcriber(s): %s.';
      case self::TYPE_OBJECT_HAS_FILE:
        return '%s added %d file(s): %s.';
      case self::TYPE_OBJECT_HAS_CONTRIBUTOR:
        return '%s added %d contributor(s): %s.';
      case self::TYPE_DASHBOARD_HAS_PANEL:
        return '%s added %d panel(s): %s.';
      case self::TYPE_PANEL_HAS_DASHBOARD:
        return '%s added %d dashboard(s): %s.';
      case self::TYPE_OBJECT_HAS_WATCHER:
        return '%s added %d watcher(s): %s.';
      case self::TYPE_SUBSCRIBED_TO_OBJECT:
      case self::TYPE_UNSUBSCRIBED_FROM_OBJECT:
      case self::TYPE_FILE_HAS_OBJECT:
      case self::TYPE_CONTRIBUTED_TO_OBJECT:
      default:
        return '%s added %d object(s): %s.';

    }
  }

  public static function getRemoveStringForEdgeType($type) {
    switch ($type) {
      case self::TYPE_OBJECT_HAS_SUBSCRIBER:
        return '%s removed %d subscriber(s): %s.';
      case self::TYPE_OBJECT_HAS_UNSUBSCRIBER:
        return '%s removed %d unsubcriber(s): %s.';
      case self::TYPE_OBJECT_HAS_FILE:
        return '%s removed %d file(s): %s.';
      case self::TYPE_OBJECT_HAS_CONTRIBUTOR:
        return '%s removed %d contributor(s): %s.';
      case self::TYPE_DASHBOARD_HAS_PANEL:
        return '%s removed %d panel(s): %s.';
      case self::TYPE_PANEL_HAS_DASHBOARD:
        return '%s removed %d dashboard(s): %s.';
      case self::TYPE_OBJECT_HAS_WATCHER:
        return '%s removed %d watcher(s): %s.';
      case self::TYPE_SUBSCRIBED_TO_OBJECT:
      case self::TYPE_UNSUBSCRIBED_FROM_OBJECT:
      case self::TYPE_FILE_HAS_OBJECT:
      case self::TYPE_CONTRIBUTED_TO_OBJECT:
      default:
        return '%s removed %d object(s): %s.';

    }
  }

  public static function getFeedStringForEdgeType($type) {
    switch ($type) {
      case self::TYPE_OBJECT_HAS_SUBSCRIBER:
        return '%s updated subscribers of %s.';
      case self::TYPE_OBJECT_HAS_UNSUBSCRIBER:
        return '%s updated unsubcribers of %s.';
      case self::TYPE_OBJECT_HAS_FILE:
        return '%s updated files of %s.';
      case self::TYPE_OBJECT_HAS_CONTRIBUTOR:
        return '%s updated contributors of %s.';
      case self::TYPE_PANEL_HAS_DASHBOARD:
        return '%s updated panels for %s.';
      case self::TYPE_PANEL_HAS_DASHBOARD:
        return '%s updated dashboards for %s.';
      case self::TYPE_OBJECT_HAS_WATCHER:
        return '%s updated watchers for %s.';
      case self::TYPE_SUBSCRIBED_TO_OBJECT:
      case self::TYPE_UNSUBSCRIBED_FROM_OBJECT:
      case self::TYPE_FILE_HAS_OBJECT:
      case self::TYPE_CONTRIBUTED_TO_OBJECT:
      default:
        return '%s updated objects of %s.';

    }
  }

}
