<?php

final class PhabricatorOwnersPathContextFreeGrammar
  extends PhutilContextFreeGrammar {

  protected function getRules() {
    return array(
      'start' => array(
        '[path]',
      ),
      'path' => array(
        '/',
        '/[directories]',
      ),
      'directories' => array(
        '[directory-name]',
        '[directories][directory-name]',
      ),
      'directory-name' => array(
        '[directory-part]/',
      ),
      'directory-part' => array(
        'src',
        'doc',
        'bin',
        'tmp',
        'log',
        'bak',
        'applications',
        'var',
        'home',
        'user',
        'lib',
        'tests',
        'webroot',
        'externals',
        'third-party',
        'libraries',
        'config',
        'media',
        'resources',
        'support',
        'scripts',
      ),
    );
  }

}
