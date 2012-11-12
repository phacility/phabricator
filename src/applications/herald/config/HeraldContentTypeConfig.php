<?php

final class HeraldContentTypeConfig {

  const CONTENT_TYPE_DIFFERENTIAL = 'differential';
  const CONTENT_TYPE_COMMIT       = 'commit';
  const CONTENT_TYPE_MERGE        = 'merge';
  const CONTENT_TYPE_OWNERS       = 'owners';

  public static function getContentTypeMap() {
    static $map = array(
      self::CONTENT_TYPE_DIFFERENTIAL   => 'Differential Revisions',
      self::CONTENT_TYPE_COMMIT         => 'Commits',
/* TODO: Deal with this
      self::CONTENT_TYPE_MERGE          => 'Merge Requests',
      self::CONTENT_TYPE_OWNERS         => 'Owners Changes',
*/
    );
    return $map;
  }
}
