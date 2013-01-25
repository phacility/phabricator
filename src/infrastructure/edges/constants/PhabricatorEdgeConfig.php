<?php

final class PhabricatorEdgeConfig extends PhabricatorEdgeConstants {

  const TABLE_NAME_EDGE       = 'edge';
  const TABLE_NAME_EDGEDATA   = 'edgedata';

  const TYPE_TASK_HAS_COMMIT            = 1;
  const TYPE_COMMIT_HAS_TASK            = 2;

  const TYPE_TASK_DEPENDS_ON_TASK       = 3;
  const TYPE_TASK_DEPENDED_ON_BY_TASK   = 4;

  const TYPE_DREV_DEPENDS_ON_DREV       = 5;
  const TYPE_DREV_DEPENDED_ON_BY_DREV   = 6;

  const TYPE_BLOG_HAS_POST              = 7;
  const TYPE_POST_HAS_BLOG              = 8;
  const TYPE_BLOG_HAS_BLOGGER           = 9;
  const TYPE_BLOGGER_HAS_BLOG           = 10;

  const TYPE_TASK_HAS_RELATED_DREV      = 11;
  const TYPE_DREV_HAS_RELATED_TASK      = 12;

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

  const TYPE_TEST_NO_CYCLE              = 9000;

  public static function getInverse($edge_type) {
    static $map = array(
      self::TYPE_TASK_HAS_COMMIT => self::TYPE_COMMIT_HAS_TASK,
      self::TYPE_COMMIT_HAS_TASK => self::TYPE_TASK_HAS_COMMIT,

      self::TYPE_TASK_DEPENDS_ON_TASK => self::TYPE_TASK_DEPENDED_ON_BY_TASK,
      self::TYPE_TASK_DEPENDED_ON_BY_TASK => self::TYPE_TASK_DEPENDS_ON_TASK,

      self::TYPE_DREV_DEPENDS_ON_DREV => self::TYPE_DREV_DEPENDED_ON_BY_DREV,
      self::TYPE_DREV_DEPENDED_ON_BY_DREV => self::TYPE_DREV_DEPENDS_ON_DREV,

      self::TYPE_BLOG_HAS_POST    => self::TYPE_POST_HAS_BLOG,
      self::TYPE_POST_HAS_BLOG    => self::TYPE_BLOG_HAS_POST,
      self::TYPE_BLOG_HAS_BLOGGER => self::TYPE_BLOGGER_HAS_BLOG,
      self::TYPE_BLOGGER_HAS_BLOG => self::TYPE_BLOG_HAS_BLOGGER,

      self::TYPE_TASK_HAS_RELATED_DREV => self::TYPE_DREV_HAS_RELATED_TASK,
      self::TYPE_DREV_HAS_RELATED_TASK => self::TYPE_TASK_HAS_RELATED_DREV,

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
    );

    return idx($map, $edge_type);
  }

  public static function shouldPreventCycles($edge_type) {
    static $map = array(
      self::TYPE_TEST_NO_CYCLE          => true,
      self::TYPE_TASK_DEPENDS_ON_TASK   => true,
      self::TYPE_DREV_DEPENDS_ON_DREV   => true,
    );
    return isset($map[$edge_type]);
  }

  public static function establishConnection($phid_type, $conn_type) {
    static $class_map = array(
      PhabricatorPHIDConstants::PHID_TYPE_TASK  => 'ManiphestTask',
      PhabricatorPHIDConstants::PHID_TYPE_CMIT  => 'PhabricatorRepository',
      PhabricatorPHIDConstants::PHID_TYPE_DREV  => 'DifferentialRevision',
      PhabricatorPHIDConstants::PHID_TYPE_FILE  => 'PhabricatorFile',
      PhabricatorPHIDConstants::PHID_TYPE_USER  => 'PhabricatorUser',
      PhabricatorPHIDConstants::PHID_TYPE_PROJ  => 'PhabricatorProject',
      PhabricatorPHIDConstants::PHID_TYPE_MLST  =>
        'PhabricatorMetaMTAMailingList',
      PhabricatorPHIDConstants::PHID_TYPE_TOBJ  => 'HarbormasterObject',
      PhabricatorPHIDConstants::PHID_TYPE_BLOG  => 'PhameBlog',
      PhabricatorPHIDConstants::PHID_TYPE_POST  => 'PhamePost',
      PhabricatorPHIDConstants::PHID_TYPE_QUES  => 'PonderQuestion',
      PhabricatorPHIDConstants::PHID_TYPE_ANSW  => 'PonderAnswer',
      PhabricatorPHIDConstants::PHID_TYPE_MOCK  => 'PholioMock',
      PhabricatorPHIDConstants::PHID_TYPE_MCRO  => 'PhabricatorFileImageMacro',
      PhabricatorPHIDConstants::PHID_TYPE_CONP  => 'ConpherenceThread',

    );

    $class = idx($class_map, $phid_type);

    if (!$class) {
      throw new Exception(
        "Edges are not available for objects of type '{$phid_type}'!");
    }

    return newv($class, array())->establishConnection($conn_type);
  }

}
