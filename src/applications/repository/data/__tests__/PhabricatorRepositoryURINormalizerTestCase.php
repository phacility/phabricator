<?php

final class PhabricatorRepositoryURINormalizerTestCase
  extends PhabricatorTestCase {

  public function testGitURINormalizer() {
    $cases = array(
      'ssh://user@domain.com/path.git' => 'path',
      'https://user@domain.com/path.git' => 'path',
      'git@domain.com:path.git' => 'path',
      'ssh://user@gitserv002.com/path.git' => 'path',
      'ssh://htaft@domain.com/path.git' => 'path',
      'ssh://user@domain.com/bananas.git' => 'bananas',
      'git@domain.com:bananas.git' => 'bananas',
      'user@domain.com:path/repo' => 'path/repo',
      'user@domain.com:path/repo/' => 'path/repo',
      'file:///path/to/local/repo.git' => 'path/to/local/repo',
      '/path/to/local/repo.git' => 'path/to/local/repo',
    );

    $type_git = PhabricatorRepositoryURINormalizer::TYPE_GIT;

    foreach ($cases as $input => $expect) {
      $normal = new PhabricatorRepositoryURINormalizer($type_git, $input);
      $this->assertEqual(
        $expect,
        $normal->getNormalizedPath(),
        pht('Normalized path for "%s".', $input));
    }
  }
}
