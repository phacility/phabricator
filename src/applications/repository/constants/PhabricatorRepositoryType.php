<?php

final class PhabricatorRepositoryType extends Phobject {

  const REPOSITORY_TYPE_GIT         = 'git';
  const REPOSITORY_TYPE_SVN         = 'svn';
  const REPOSITORY_TYPE_MERCURIAL   = 'hg';
  const REPOSITORY_TYPE_PERFORCE    = 'p4';

  public static function getAllRepositoryTypes() {
    static $map = array(
      self::REPOSITORY_TYPE_GIT       => 'Git',
      self::REPOSITORY_TYPE_SVN       => 'Subversion',
      self::REPOSITORY_TYPE_MERCURIAL => 'Mercurial',
    );
    return $map;
  }

  public static function getNameForRepositoryType($type) {
    $map = self::getAllRepositoryTypes();
    return idx($map, $type, pht('Unknown'));
  }

}
