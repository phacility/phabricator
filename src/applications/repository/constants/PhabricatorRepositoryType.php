<?php

final class PhabricatorRepositoryType extends Phobject {

  const REPOSITORY_TYPE_GIT         = 'git';
  const REPOSITORY_TYPE_SVN         = 'svn';
  const REPOSITORY_TYPE_MERCURIAL   = 'hg';

  public static function getAllRepositoryTypes() {
    $map = self::getRepositoryTypeMap();
    return ipull($map, 'name');
  }

  public static function getNameForRepositoryType($type) {
    $spec = self::getRepositoryTypeSpec($type);
    return idx($spec, 'name', pht('Unknown ("%s")', $type));
  }

  public static function getRepositoryTypeSpec($type) {
    $map = self::getRepositoryTypeMap();
    return idx($map, $type, array());
  }

  public static function getRepositoryTypeMap() {
    return array(
      self::REPOSITORY_TYPE_GIT => array(
        'name' => pht('Git'),
        'icon' => 'fa-git',
        'image' => 'repo/repo-git.png',
        'create.header' => pht('Create Git Repository'),
        'create.subheader' => pht('Create a new Git repository.'),
      ),
      self::REPOSITORY_TYPE_MERCURIAL => array(
        'name' => pht('Mercurial'),
        'icon' => 'fa-code-fork',
        'image' => 'repo/repo-hg.png',
        'create.header' => pht('Create Mercurial Repository'),
        'create.subheader' => pht('Create a new Mercurial repository.'),
      ),
      self::REPOSITORY_TYPE_SVN => array(
        'name' => pht('Subversion'),
        'icon' => 'fa-database',
        'image' => 'repo/repo-svn.png',
        'create.header' => pht('Create Subversion Repository'),
        'create.subheader' => pht('Create a new Subversion repository.'),
      ),
    );
  }

}
