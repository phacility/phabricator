<?php

final class DifferentialChangeType extends Phobject {

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

  public static function getIconForFileType($type) {
    static $icons = array(
      self::FILE_TEXT => 'fa-file-text-o',
      self::FILE_IMAGE => 'fa-file-image-o',
      self::FILE_BINARY => 'fa-file',
      self::FILE_DIRECTORY => 'fa-folder',
      self::FILE_SYMLINK => 'fa-link',
      self::FILE_DELETED => 'fa-file',
      self::FILE_NORMAL => 'fa-file-text-o',
      self::FILE_SUBMODULE => 'fa-folder-open-o',
    );

    return idx($icons, $type, 'fa-file');
  }

  public static function getIconColorForFileType($type) {
    static $icons = array(
      self::FILE_TEXT => 'bluetext',
      self::FILE_IMAGE => 'bluetext',
      self::FILE_BINARY => 'green',
      self::FILE_DIRECTORY => 'bluetext',
      self::FILE_SYMLINK => 'bluetext',
      self::FILE_DELETED => 'red',
      self::FILE_NORMAL => 'bluetext',
      self::FILE_SUBMODULE => 'bluetext',
    );

    return idx($icons, $type, 'black');
  }

  public static function isOldLocationChangeType($type) {
    static $types = array(
      self::TYPE_MOVE_AWAY  => true,
      self::TYPE_COPY_AWAY  => true,
      self::TYPE_MULTICOPY  => true,
    );
    return isset($types[$type]);
  }

  public static function isNewLocationChangeType($type) {
    static $types = array(
      self::TYPE_MOVE_HERE  => true,
      self::TYPE_COPY_HERE  => true,
    );
    return isset($types[$type]);
  }

  public static function isDeleteChangeType($type) {
    static $types = array(
      self::TYPE_DELETE     => true,
      self::TYPE_MOVE_AWAY  => true,
      self::TYPE_MULTICOPY  => true,
    );
    return isset($types[$type]);
  }

  public static function isCreateChangeType($type) {
    static $types = array(
      self::TYPE_ADD        => true,
      self::TYPE_COPY_HERE  => true,
      self::TYPE_MOVE_HERE  => true,
    );
    return isset($types[$type]);
  }

  public static function isModifyChangeType($type) {
    static $types = array(
      self::TYPE_CHANGE     => true,
    );
    return isset($types[$type]);
  }

  public static function getFullNameForChangeType($type) {
    $types = array(
      self::TYPE_ADD        => pht('Added'),
      self::TYPE_CHANGE     => pht('Modified'),
      self::TYPE_DELETE     => pht('Deleted'),
      self::TYPE_MOVE_AWAY  => pht('Moved Away'),
      self::TYPE_COPY_AWAY  => pht('Copied Away'),
      self::TYPE_MOVE_HERE  => pht('Moved Here'),
      self::TYPE_COPY_HERE  => pht('Copied Here'),
      self::TYPE_MULTICOPY  => pht('Deleted After Multiple Copy'),
      self::TYPE_MESSAGE    => pht('Commit Message'),
      self::TYPE_CHILD      => pht('Contents Modified'),
    );
    return idx($types, coalesce($type, '?'), pht('Unknown'));
  }

}
