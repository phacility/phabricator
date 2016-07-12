<?php

final class PhabricatorRepositoryVersion extends Phobject {

  public static function getMercurialVersion() {
    list($err, $stdout, $stderr) = exec_manual('hg --version --quiet');

    // NOTE: At least on OSX, recent versions of Mercurial report this
    // string in this format:
    //
    //   Mercurial Distributed SCM (version 3.1.1+20140916)

    $matches = null;
    $pattern = '/^Mercurial Distributed SCM \(version ([\d.]+)/m';
    if (preg_match($pattern, $stdout, $matches)) {
      return $matches[1];
    }

    return null;
  }

  /**
   * The `locate` command is deprecated as of Mercurial 3.2, to be
   * replaced with `files` command, which supports most of the same
   * arguments. This determines whether the new `files` command should
   * be used instead of the `locate` command.
   *
   * @param string  $mercurial_version - The current version of mercurial
   *   which can be retrieved by calling:
   *   PhabricatorRepositoryVersion::getMercurialVersion()
   *
   * @return boolean  True if the version of Mercurial is new enough to support
   *   the `files` command, or false if otherwise.
   */
  public static function isMercurialFilesCommandAvailable($mercurial_version) {
    $min_version_for_files = '3.2';
    return version_compare($mercurial_version, $min_version_for_files, '>=');
  }

}
