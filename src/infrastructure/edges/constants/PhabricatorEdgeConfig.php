<?php

final class PhabricatorEdgeConfig extends PhabricatorEdgeConstants {

  const TABLE_NAME_EDGE       = 'edge';
  const TABLE_NAME_EDGEDATA   = 'edgedata';

  const TYPE_TASK_DEPENDS_ON_TASK       = 3;
  const TYPE_TASK_DEPENDED_ON_BY_TASK   = 4;

  const TYPE_DREV_DEPENDS_ON_DREV       = 5;
  const TYPE_DREV_DEPENDED_ON_BY_DREV   = 6;

  const TYPE_BLOG_HAS_POST              = 7;
  const TYPE_POST_HAS_BLOG              = 8;
  const TYPE_BLOG_HAS_BLOGGER           = 9;
  const TYPE_BLOGGER_HAS_BLOG           = 10;

  const TYPE_PROJ_MEMBER                = 13;
  const TYPE_MEMBER_OF_PROJ             = 14;

  const TYPE_COMMIT_HAS_PROJECT         = 15;
  const TYPE_PROJECT_HAS_COMMIT         = 16;

  const TYPE_QUESTION_HAS_VOTING_USER   = 17;
  const TYPE_VOTING_USER_HAS_QUESTION   = 18;
  const TYPE_ANSWER_HAS_VOTING_USER     = 19;
  const TYPE_VOTING_USER_HAS_ANSWER     = 20;

  const TYPE_OBJECT_HAS_SUBSCRIBER      = 21;
  const TYPE_SUBSCRIBED_TO_OBJECT       = 22;

  const TYPE_OBJECT_HAS_UNSUBSCRIBER    = 23;
  const TYPE_UNSUBSCRIBED_FROM_OBJECT   = 24;

  const TYPE_OBJECT_HAS_FILE            = 25;
  const TYPE_FILE_HAS_OBJECT            = 26;

  const TYPE_ACCOUNT_HAS_MEMBER         = 27;
  const TYPE_MEMBER_HAS_ACCOUNT         = 28;

  const TYPE_PURCAHSE_HAS_CHARGE        = 29;
  const TYPE_CHARGE_HAS_PURCHASE        = 30;

  const TYPE_DREV_HAS_COMMIT            = 31;
  const TYPE_COMMIT_HAS_DREV            = 32;

  const TYPE_OBJECT_HAS_CONTRIBUTOR     = 33;
  const TYPE_CONTRIBUTED_TO_OBJECT      = 34;

  const TYPE_DREV_HAS_REVIEWER          = 35;
  const TYPE_REVIEWER_FOR_DREV          = 36;

  const TYPE_MOCK_HAS_TASK              = 37;
  const TYPE_TASK_HAS_MOCK              = 38;

  const TYPE_OBJECT_USES_CREDENTIAL     = 39;
  const TYPE_CREDENTIAL_USED_BY_OBJECT  = 40;

  const TYPE_DASHBOARD_HAS_PANEL        = 45;
  const TYPE_PANEL_HAS_DASHBOARD        = 46;

  const TYPE_OBJECT_HAS_WATCHER         = 47;
  const TYPE_WATCHER_HAS_OBJECT         = 48;

  const TYPE_OBJECT_NEEDS_SIGNATURE     = 49;
  const TYPE_SIGNATURE_NEEDED_BY_OBJECT = 50;

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
      self::TYPE_TASK_DEPENDS_ON_TASK => self::TYPE_TASK_DEPENDED_ON_BY_TASK,
      self::TYPE_TASK_DEPENDED_ON_BY_TASK => self::TYPE_TASK_DEPENDS_ON_TASK,

      self::TYPE_DREV_DEPENDS_ON_DREV => self::TYPE_DREV_DEPENDED_ON_BY_DREV,
      self::TYPE_DREV_DEPENDED_ON_BY_DREV => self::TYPE_DREV_DEPENDS_ON_DREV,

      self::TYPE_BLOG_HAS_POST    => self::TYPE_POST_HAS_BLOG,
      self::TYPE_POST_HAS_BLOG    => self::TYPE_BLOG_HAS_POST,
      self::TYPE_BLOG_HAS_BLOGGER => self::TYPE_BLOGGER_HAS_BLOG,
      self::TYPE_BLOGGER_HAS_BLOG => self::TYPE_BLOG_HAS_BLOGGER,

      self::TYPE_PROJ_MEMBER => self::TYPE_MEMBER_OF_PROJ,
      self::TYPE_MEMBER_OF_PROJ => self::TYPE_PROJ_MEMBER,

      self::TYPE_COMMIT_HAS_PROJECT => self::TYPE_PROJECT_HAS_COMMIT,
      self::TYPE_PROJECT_HAS_COMMIT => self::TYPE_COMMIT_HAS_PROJECT,

      self::TYPE_QUESTION_HAS_VOTING_USER =>
        self::TYPE_VOTING_USER_HAS_QUESTION,
      self::TYPE_VOTING_USER_HAS_QUESTION =>
        self::TYPE_QUESTION_HAS_VOTING_USER,
      self::TYPE_ANSWER_HAS_VOTING_USER => self::TYPE_VOTING_USER_HAS_ANSWER,
      self::TYPE_VOTING_USER_HAS_ANSWER => self::TYPE_ANSWER_HAS_VOTING_USER,

      self::TYPE_OBJECT_HAS_SUBSCRIBER => self::TYPE_SUBSCRIBED_TO_OBJECT,
      self::TYPE_SUBSCRIBED_TO_OBJECT => self::TYPE_OBJECT_HAS_SUBSCRIBER,

      self::TYPE_OBJECT_HAS_UNSUBSCRIBER => self::TYPE_UNSUBSCRIBED_FROM_OBJECT,
      self::TYPE_UNSUBSCRIBED_FROM_OBJECT => self::TYPE_OBJECT_HAS_UNSUBSCRIBER,

      self::TYPE_OBJECT_HAS_FILE => self::TYPE_FILE_HAS_OBJECT,
      self::TYPE_FILE_HAS_OBJECT => self::TYPE_OBJECT_HAS_FILE,

      self::TYPE_ACCOUNT_HAS_MEMBER => self::TYPE_MEMBER_HAS_ACCOUNT,
      self::TYPE_MEMBER_HAS_ACCOUNT => self::TYPE_ACCOUNT_HAS_MEMBER,

      self::TYPE_DREV_HAS_COMMIT => self::TYPE_COMMIT_HAS_DREV,
      self::TYPE_COMMIT_HAS_DREV => self::TYPE_DREV_HAS_COMMIT,

      self::TYPE_OBJECT_HAS_CONTRIBUTOR => self::TYPE_CONTRIBUTED_TO_OBJECT,
      self::TYPE_CONTRIBUTED_TO_OBJECT => self::TYPE_OBJECT_HAS_CONTRIBUTOR,

      self::TYPE_TASK_HAS_MOCK => self::TYPE_MOCK_HAS_TASK,
      self::TYPE_MOCK_HAS_TASK => self::TYPE_TASK_HAS_MOCK,

      self::TYPE_PHOB_HAS_ASANATASK => self::TYPE_ASANATASK_HAS_PHOB,
      self::TYPE_ASANATASK_HAS_PHOB => self::TYPE_PHOB_HAS_ASANATASK,

      self::TYPE_PHOB_HAS_ASANASUBTASK => self::TYPE_ASANASUBTASK_HAS_PHOB,
      self::TYPE_ASANASUBTASK_HAS_PHOB => self::TYPE_PHOB_HAS_ASANASUBTASK,

      self::TYPE_DREV_HAS_REVIEWER => self::TYPE_REVIEWER_FOR_DREV,
      self::TYPE_REVIEWER_FOR_DREV => self::TYPE_DREV_HAS_REVIEWER,

      self::TYPE_PHOB_HAS_JIRAISSUE => self::TYPE_JIRAISSUE_HAS_PHOB,
      self::TYPE_JIRAISSUE_HAS_PHOB => self::TYPE_PHOB_HAS_JIRAISSUE,

      self::TYPE_OBJECT_USES_CREDENTIAL => self::TYPE_CREDENTIAL_USED_BY_OBJECT,
      self::TYPE_CREDENTIAL_USED_BY_OBJECT => self::TYPE_OBJECT_USES_CREDENTIAL,

      self::TYPE_PANEL_HAS_DASHBOARD => self::TYPE_DASHBOARD_HAS_PANEL,
      self::TYPE_DASHBOARD_HAS_PANEL => self::TYPE_PANEL_HAS_DASHBOARD,

      self::TYPE_OBJECT_HAS_WATCHER => self::TYPE_WATCHER_HAS_OBJECT,
      self::TYPE_WATCHER_HAS_OBJECT => self::TYPE_OBJECT_HAS_WATCHER,

      self::TYPE_OBJECT_NEEDS_SIGNATURE =>
        self::TYPE_SIGNATURE_NEEDED_BY_OBJECT,
      self::TYPE_SIGNATURE_NEEDED_BY_OBJECT =>
        self::TYPE_OBJECT_NEEDS_SIGNATURE,
    );

    return idx($map, $edge_type);
  }

  private static function shouldPreventCycles($edge_type) {
    static $map = array(
      self::TYPE_TEST_NO_CYCLE          => true,
      self::TYPE_TASK_DEPENDS_ON_TASK   => true,
      self::TYPE_DREV_DEPENDS_ON_DREV   => true,
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
      PhabricatorPHIDConstants::PHID_TYPE_ACNT  => 'PhortuneAccount',
      PhabricatorPHIDConstants::PHID_TYPE_PRCH  => 'PhortunePurchase',
      PhabricatorPHIDConstants::PHID_TYPE_CHRG  => 'PhortuneCharge',
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
      case self::TYPE_PROJECT_HAS_COMMIT:
      case self::TYPE_DREV_HAS_COMMIT:
        return '%s edited commit(s), added %d: %s; removed %d: %s.';
      case self::TYPE_TASK_DEPENDS_ON_TASK:
      case self::TYPE_TASK_DEPENDED_ON_BY_TASK:
      case self::TYPE_MOCK_HAS_TASK:
        return '%s edited task(s), added %d: %s; removed %d: %s.';
      case self::TYPE_DREV_DEPENDS_ON_DREV:
      case self::TYPE_DREV_DEPENDED_ON_BY_DREV:
      case self::TYPE_COMMIT_HAS_DREV:
      case self::TYPE_REVIEWER_FOR_DREV:
        return '%s edited revision(s), added %d: %s; removed %d: %s.';
      case self::TYPE_BLOG_HAS_POST:
        return '%s edited post(s), added %d: %s; removed %d: %s.';
      case self::TYPE_POST_HAS_BLOG:
      case self::TYPE_BLOGGER_HAS_BLOG:
        return '%s edited blog(s), added %d: %s; removed %d: %s.';
      case self::TYPE_BLOG_HAS_BLOGGER:
        return '%s edited blogger(s), added %d: %s; removed %d: %s.';
      case self::TYPE_PROJ_MEMBER:
        return '%s edited member(s), added %d: %s; removed %d: %s.';
      case self::TYPE_MEMBER_OF_PROJ:
      case self::TYPE_COMMIT_HAS_PROJECT:
        return '%s edited project(s), added %d: %s; removed %d: %s.';
      case self::TYPE_QUESTION_HAS_VOTING_USER:
      case self::TYPE_ANSWER_HAS_VOTING_USER:
        return '%s edited voting user(s), added %d: %s; removed %d: %s.';
      case self::TYPE_VOTING_USER_HAS_QUESTION:
        return '%s edited question(s), added %d: %s; removed %d: %s.';
      case self::TYPE_VOTING_USER_HAS_ANSWER:
        return '%s edited answer(s), added %d: %s; removed %d: %s.';
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
      case self::TYPE_ACCOUNT_HAS_MEMBER:
        return '%s edited member(s), added %d: %s; removed %d: %s.';
      case self::TYPE_MEMBER_HAS_ACCOUNT:
        return '%s edited account(s), added %d: %s; removed %d: %s.';
      case self::TYPE_PURCAHSE_HAS_CHARGE:
        return '%s edited charge(s), added %d: %s; removed %d: %s.';
      case self::TYPE_CHARGE_HAS_PURCHASE:
        return '%s edited purchase(s), added %d: %s; removed %d: %s.';
      case self::TYPE_OBJECT_HAS_CONTRIBUTOR:
        return '%s edited contributor(s), added %d: %s; removed %d: %s.';
      case self::TYPE_DREV_HAS_REVIEWER:
        return '%s edited reviewer(s), added %d: %s; removed %d: %s.';
      case self::TYPE_TASK_HAS_MOCK:
        return '%s edited mock(s), added %d: %s; removed %d: %s.';
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
      case self::TYPE_PROJECT_HAS_COMMIT:
      case self::TYPE_DREV_HAS_COMMIT:
        return '%s added %d commit(s): %s.';
      case self::TYPE_TASK_DEPENDS_ON_TASK:
        return '%s added %d blocking task(s): %s.';
      case self::TYPE_DREV_DEPENDS_ON_DREV:
        return '%s added %d dependencie(s): %s.';
      case self::TYPE_TASK_DEPENDED_ON_BY_TASK:
        return '%s added %d blocked task(s): %s.';
      case self::TYPE_MOCK_HAS_TASK:
        return '%s added %d task(s): %s.';
      case self::TYPE_DREV_DEPENDED_ON_BY_DREV:
      case self::TYPE_COMMIT_HAS_DREV:
      case self::TYPE_REVIEWER_FOR_DREV:
        return '%s added %d revision(s): %s.';
      case self::TYPE_BLOG_HAS_POST:
        return '%s added %d post(s): %s.';
      case self::TYPE_POST_HAS_BLOG:
      case self::TYPE_BLOGGER_HAS_BLOG:
        return '%s added %d blog(s): %s.';
      case self::TYPE_BLOG_HAS_BLOGGER:
        return '%s added %d blogger(s): %s.';
      case self::TYPE_PROJ_MEMBER:
        return '%s added %d member(s): %s.';
      case self::TYPE_MEMBER_OF_PROJ:
      case self::TYPE_COMMIT_HAS_PROJECT:
        return '%s added %d project(s): %s.';
      case self::TYPE_QUESTION_HAS_VOTING_USER:
      case self::TYPE_ANSWER_HAS_VOTING_USER:
        return '%s added %d voting user(s): %s.';
      case self::TYPE_VOTING_USER_HAS_QUESTION:
        return '%s added %d question(s): %s.';
      case self::TYPE_VOTING_USER_HAS_ANSWER:
        return '%s added %d answer(s): %s.';
      case self::TYPE_OBJECT_HAS_SUBSCRIBER:
        return '%s added %d subscriber(s): %s.';
      case self::TYPE_OBJECT_HAS_UNSUBSCRIBER:
        return '%s added %d unsubcriber(s): %s.';
      case self::TYPE_OBJECT_HAS_FILE:
        return '%s added %d file(s): %s.';
      case self::TYPE_ACCOUNT_HAS_MEMBER:
        return '%s added %d member(s): %s.';
      case self::TYPE_MEMBER_HAS_ACCOUNT:
        return '%s added %d account(s): %s.';
      case self::TYPE_PURCAHSE_HAS_CHARGE:
        return '%s added %d charge(s): %s.';
      case self::TYPE_CHARGE_HAS_PURCHASE:
        return '%s added %d purchase(s): %s.';
      case self::TYPE_OBJECT_HAS_CONTRIBUTOR:
        return '%s added %d contributor(s): %s.';
      case self::TYPE_DREV_HAS_REVIEWER:
        return '%s added %d reviewer(s): %s.';
      case self::TYPE_TASK_HAS_MOCK:
        return '%s added %d mock(s): %s.';
      case self::TYPE_DASHBOARD_HAS_PANEL:
        return '%s added %d panel(s): %s.';
      case self::TYPE_PANEL_HAS_DASHBOARD:
        return '%s added %d dashboard(s): %s.';
      case self::TYPE_OBJECT_HAS_WATCHER:
        return '%s added %d watcher(s): %s.';
      case self::TYPE_OBJECT_NEEDS_SIGNATURE:
        return '%s added %d required legal document(s): %s.';
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
      case self::TYPE_PROJECT_HAS_COMMIT:
      case self::TYPE_DREV_HAS_COMMIT:
        return '%s removed %d commit(s): %s.';
      case self::TYPE_TASK_DEPENDS_ON_TASK:
        return '%s removed %d blocking task(s): %s.';
      case self::TYPE_TASK_DEPENDED_ON_BY_TASK:
        return '%s removed %d blocked task(s): %s.';
      case self::TYPE_MOCK_HAS_TASK:
        return '%s removed %d task(s): %s.';
      case self::TYPE_DREV_DEPENDS_ON_DREV:
      case self::TYPE_DREV_DEPENDED_ON_BY_DREV:
      case self::TYPE_COMMIT_HAS_DREV:
      case self::TYPE_REVIEWER_FOR_DREV:
        return '%s removed %d revision(s): %s.';
      case self::TYPE_BLOG_HAS_POST:
        return '%s removed %d post(s): %s.';
      case self::TYPE_POST_HAS_BLOG:
      case self::TYPE_BLOGGER_HAS_BLOG:
        return '%s removed %d blog(s): %s.';
      case self::TYPE_BLOG_HAS_BLOGGER:
        return '%s removed %d blogger(s): %s.';
      case self::TYPE_PROJ_MEMBER:
        return '%s removed %d member(s): %s.';
      case self::TYPE_MEMBER_OF_PROJ:
      case self::TYPE_COMMIT_HAS_PROJECT:
        return '%s removed %d project(s): %s.';
      case self::TYPE_QUESTION_HAS_VOTING_USER:
      case self::TYPE_ANSWER_HAS_VOTING_USER:
        return '%s removed %d voting user(s): %s.';
      case self::TYPE_VOTING_USER_HAS_QUESTION:
        return '%s removed %d question(s): %s.';
      case self::TYPE_VOTING_USER_HAS_ANSWER:
        return '%s removed %d answer(s): %s.';
      case self::TYPE_OBJECT_HAS_SUBSCRIBER:
        return '%s removed %d subscriber(s): %s.';
      case self::TYPE_OBJECT_HAS_UNSUBSCRIBER:
        return '%s removed %d unsubcriber(s): %s.';
      case self::TYPE_OBJECT_HAS_FILE:
        return '%s removed %d file(s): %s.';
      case self::TYPE_ACCOUNT_HAS_MEMBER:
        return '%s removed %d member(s): %s.';
      case self::TYPE_MEMBER_HAS_ACCOUNT:
        return '%s removed %d account(s): %s.';
      case self::TYPE_PURCAHSE_HAS_CHARGE:
        return '%s removed %d charge(s): %s.';
      case self::TYPE_CHARGE_HAS_PURCHASE:
        return '%s removed %d purchase(s): %s.';
      case self::TYPE_OBJECT_HAS_CONTRIBUTOR:
        return '%s removed %d contributor(s): %s.';
      case self::TYPE_DREV_HAS_REVIEWER:
        return '%s removed %d reviewer(s): %s.';
      case self::TYPE_TASK_HAS_MOCK:
        return '%s removed %d mock(s): %s.';
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
      case self::TYPE_PROJECT_HAS_COMMIT:
      case self::TYPE_DREV_HAS_COMMIT:
        return '%s updated commits of %s.';
      case self::TYPE_TASK_DEPENDS_ON_TASK:
      case self::TYPE_TASK_DEPENDED_ON_BY_TASK:
      case self::TYPE_MOCK_HAS_TASK:
        return '%s updated tasks of %s.';
      case self::TYPE_DREV_DEPENDS_ON_DREV:
      case self::TYPE_DREV_DEPENDED_ON_BY_DREV:
      case self::TYPE_COMMIT_HAS_DREV:
      case self::TYPE_REVIEWER_FOR_DREV:
        return '%s updated revisions of %s.';
      case self::TYPE_BLOG_HAS_POST:
        return '%s updated posts of %s.';
      case self::TYPE_POST_HAS_BLOG:
      case self::TYPE_BLOGGER_HAS_BLOG:
        return '%s updated blogs of %s.';
      case self::TYPE_BLOG_HAS_BLOGGER:
        return '%s updated bloggers of %s.';
      case self::TYPE_PROJ_MEMBER:
        return '%s updated members of %s.';
      case self::TYPE_MEMBER_OF_PROJ:
      case self::TYPE_COMMIT_HAS_PROJECT:
        return '%s updated projects of %s.';
      case self::TYPE_QUESTION_HAS_VOTING_USER:
      case self::TYPE_ANSWER_HAS_VOTING_USER:
        return '%s updated voting users of %s.';
      case self::TYPE_VOTING_USER_HAS_QUESTION:
        return '%s updated questions of %s.';
      case self::TYPE_VOTING_USER_HAS_ANSWER:
        return '%s updated answers of %s.';
      case self::TYPE_OBJECT_HAS_SUBSCRIBER:
        return '%s updated subscribers of %s.';
      case self::TYPE_OBJECT_HAS_UNSUBSCRIBER:
        return '%s updated unsubcribers of %s.';
      case self::TYPE_OBJECT_HAS_FILE:
        return '%s updated files of %s.';
      case self::TYPE_ACCOUNT_HAS_MEMBER:
        return '%s updated members of %s.';
      case self::TYPE_MEMBER_HAS_ACCOUNT:
        return '%s updated accounts of %s.';
      case self::TYPE_PURCAHSE_HAS_CHARGE:
        return '%s updated charges of %s.';
      case self::TYPE_CHARGE_HAS_PURCHASE:
        return '%s updated purchases of %s.';
      case self::TYPE_OBJECT_HAS_CONTRIBUTOR:
        return '%s updated contributors of %s.';
      case self::TYPE_DREV_HAS_REVIEWER:
        return '%s updated reviewers of %s.';
      case self::TYPE_TASK_HAS_MOCK:
        return '%s updated mocks of %s.';
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
