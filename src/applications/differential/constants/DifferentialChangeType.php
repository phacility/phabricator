<?php

final class DifferentialChangeType {

  const TYPE_ADD        = 1;
  const TYPE_CHANGE     = 2;
  const TYPE_DELETE     = 3;
  const TYPE_MOVE_AWAY  = 4;
  const TYPE_COPY_AWAY  = 5;
  const TYPE_MOVE_HERE  = 6;
  const TYPE_COPY_HERE  = 7;
  const TYPE_MULTICOPY  = 8;
  const TYPE_MESSAGE    = 9;
  const TYPE_CHILD      = 10;

  const FILE_TEXT       = 1;
  const FILE_IMAGE      = 2;
  const FILE_BINARY     = 3;
  const FILE_DIRECTORY  = 4;
  const FILE_SYMLINK    = 5;
  const FILE_DELETED    = 6;
  const FILE_NORMAL     = 7;
  const FILE_SUBMODULE  = 8;

  public static function getSummaryCharacterForChangeType($type) {
    static $types = array(
      self::TYPE_ADD        => 'A',
      self::TYPE_CHANGE     => 'M',
      self::TYPE_DELETE     => 'D',
      self::TYPE_MOVE_AWAY  => 'V',
      self::TYPE_COPY_AWAY  => 'P',
      self::TYPE_MOVE_HERE  => 'V',
      self::TYPE_COPY_HERE  => 'P',
      self::TYPE_MULTICOPY  => 'P',
      self::TYPE_MESSAGE    => 'Q',
      self::TYPE_CHILD      => '@',
    );
    return idx($types, coalesce($type, '?'), '~');
  }

  public static function getShortNameForFileType($type) {
    static $names = array(
      self::FILE_TEXT       => null,
      self::FILE_DIRECTORY  => 'dir',
      self::FILE_IMAGE      => 'img',
      self::FILE_BINARY     => 'bin',
      self::FILE_SYMLINK    => 'sym',
      self::FILE_SUBMODULE  => 'sub',
    );
    return idx($names, coalesce($type, '?'), '???');
  }

  public static function isOldLocationChangeType($type) {
    static $types = array(
      DifferentialChangeType::TYPE_MOVE_AWAY  => true,
      DifferentialChangeType::TYPE_COPY_AWAY  => true,
      DifferentialChangeType::TYPE_MULTICOPY  => true,
    );
    return isset($types[$type]);
  }

  public static function isNewLocationChangeType($type) {
    static $types = array(
      DifferentialChangeType::TYPE_MOVE_HERE  => true,
      DifferentialChangeType::TYPE_COPY_HERE  => true,
    );
    return isset($types[$type]);
  }

  public static function isDeleteChangeType($type) {
    static $types = array(
      DifferentialChangeType::TYPE_DELETE     => true,
      DifferentialChangeType::TYPE_MOVE_AWAY  => true,
      DifferentialChangeType::TYPE_MULTICOPY  => true,
    );
    return isset($types[$type]);
  }

  public static function isCreateChangeType($type) {
    static $types = array(
      DifferentialChangeType::TYPE_ADD        => true,
      DifferentialChangeType::TYPE_COPY_HERE  => true,
      DifferentialChangeType::TYPE_MOVE_HERE  => true,
    );
    return isset($types[$type]);
  }

  public static function isModifyChangeType($type) {
    static $types = array(
      DifferentialChangeType::TYPE_CHANGE     => true,
    );
    return isset($types[$type]);
  }

  public static function getFullNameForChangeType($type) {
    static $types = array(
      self::TYPE_ADD        => 'Added',
      self::TYPE_CHANGE     => 'Modified',
      self::TYPE_DELETE     => 'Deleted',
      self::TYPE_MOVE_AWAY  => 'Moved Away',
      self::TYPE_COPY_AWAY  => 'Copied Away',
      self::TYPE_MOVE_HERE  => 'Moved Here',
      self::TYPE_COPY_HERE  => 'Copied Here',
      self::TYPE_MULTICOPY  => 'Deleted After Multiple Copy',
      self::TYPE_MESSAGE    => 'Commit Message',
      self::TYPE_CHILD      => 'Contents Modified',
    );
    return idx($types, coalesce($type, '?'), 'Unknown');
  }

}
