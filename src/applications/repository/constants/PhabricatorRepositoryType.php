<?php

final class PhabricatorRepositoryType extends Phobject {

  const REPOSITORY_TYPE_GIT         = 'git';
  const REPOSITORY_TYPE_SVN         = 'svn';
  const REPOSITORY_TYPE_MERCURIAL   = 'hg';

  public static function getAllRepositoryTypes() {
    $map = array(
      self::REPOSITORY_TYPE_GIT       => pht('Git'),
      self::REPOSITORY_TYPE_MERCURIAL => pht('Mercurial'),
      self::REPOSITORY_TYPE_SVN       => pht('Subversion'),
    );
    return $map;
  }

  public static function getNameForRepositoryType($type) {
    $map = self::getAllRepositoryTypes();
    return idx($map, $type, pht('Unknown'));
  }

}
