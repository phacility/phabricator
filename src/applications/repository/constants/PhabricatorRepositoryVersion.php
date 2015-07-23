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

}
