<?php

final class PhabricatorRepositoryTestCase
  extends PhabricatorTestCase {

  public function testRepositoryURIProtocols() {
    $tests = array(
      '/path/to/repo'               => 'file',
      'file:///path/to/repo'        => 'file',
      'ssh://user@domain.com/path'  => 'ssh',
      'git@example.com:path'        => 'ssh',
      'git://git@example.com/path'  => 'git',
      'svn+ssh://example.com/path'  => 'svn+ssh',
      'https://example.com/repo/'   => 'https',
      'http://example.com/'         => 'http',
      'https://user@example.com/'   => 'https',
    );

    foreach ($tests as $uri => $expect) {
      $repository = new PhabricatorRepository();
      $repository->setDetail('remote-uri', $uri);

      $this->assertEqual(
        $expect,
        $repository->getRemoteProtocol(),
        pht("Protocol for '%s'.", $uri));
    }
  }

  public function testBranchFilter() {
    $git = PhabricatorRepositoryType::REPOSITORY_TYPE_GIT;

    $repo = new PhabricatorRepository();
    $repo->setVersionControlSystem($git);

    $this->assertTrue(
      $repo->shouldTrackBranch('imaginary'),
      pht('Track all branches by default.'));

    $repo->setDetail(
      'branch-filter',
      array(
        'master' => true,
      ));

    $this->assertTrue(
      $repo->shouldTrackBranch('master'),
      pht('Track listed branches.'));

    $this->assertFalse(
      $repo->shouldTrackBranch('imaginary'),
      pht('Do not track unlisted branches.'));
  }

  public function testSubversionPathInfo() {
    $svn = PhabricatorRepositoryType::REPOSITORY_TYPE_SVN;

    $repo = new PhabricatorRepository();
    $repo->setVersionControlSystem($svn);

    $repo->setDetail('remote-uri', 'http://svn.example.com/repo');
    $this->assertEqual(
      'http://svn.example.com/repo',
      $repo->getSubversionPathURI());

    $repo->setDetail('remote-uri', 'http://svn.example.com/repo/');
    $this->assertEqual(
      'http://svn.example.com/repo',
      $repo->getSubversionPathURI());

    $repo->setDetail('hosting-enabled', true);

    $repo->setLocalPath('/var/repo/SVN');
    $this->assertEqual(
      'file:///var/repo/SVN',
      $repo->getSubversionPathURI());

    $repo->setLocalPath('/var/repo/SVN/');
    $this->assertEqual(
      'file:///var/repo/SVN',
      $repo->getSubversionPathURI());

    $this->assertEqual(
      'file:///var/repo/SVN/a@',
      $repo->getSubversionPathURI('a'));

    $this->assertEqual(
      'file:///var/repo/SVN/a@1',
      $repo->getSubversionPathURI('a', 1));

    $this->assertEqual(
      'file:///var/repo/SVN/%3F@22',
      $repo->getSubversionPathURI('?', 22));

    $repo->setDetail('svn-subpath', 'quack/trunk/');

    $this->assertEqual(
      'file:///var/repo/SVN/quack/trunk/@',
      $repo->getSubversionBaseURI());

    $this->assertEqual(
      'file:///var/repo/SVN/quack/trunk/@HEAD',
      $repo->getSubversionBaseURI('HEAD'));

  }

  public function testFilterMercurialDebugOutput() {
    $map = array(
      '' => '',

      "quack\n" => "quack\n",

      "ignoring untrusted configuration option x.y = z\nquack\n" =>
        "quack\n",

      "ignoring untrusted configuration option x.y = z\n".
      "ignoring untrusted configuration option x.y = z\n".
      "quack\n" =>
        "quack\n",

      "ignoring untrusted configuration option x.y = z\n".
      "ignoring untrusted configuration option x.y = z\n".
      "ignoring untrusted configuration option x.y = z\n".
      "quack\n" =>
        "quack\n",

      "quack\n".
      "ignoring untrusted configuration option x.y = z\n".
      "ignoring untrusted configuration option x.y = z\n".
      "ignoring untrusted configuration option x.y = z\n" =>
        "quack\n",

      "ignoring untrusted configuration option x.y = z\n".
      "ignoring untrusted configuration option x.y = z\n".
      "duck\n".
      "ignoring untrusted configuration option x.y = z\n".
      "ignoring untrusted configuration option x.y = z\n".
      "bread\n".
      "ignoring untrusted configuration option x.y = z\n".
      "quack\n" =>
        "duck\nbread\nquack\n",

      "ignoring untrusted configuration option x.y = z\n".
      "duckignoring untrusted configuration option x.y = z\n".
      "quack" =>
        'duckquack',
    );

    foreach ($map as $input => $expect) {
      $actual = DiffusionMercurialCommandEngine::filterMercurialDebugOutput(
        $input);
      $this->assertEqual($expect, $actual, $input);
    }
  }

  public function testRepositoryShortNameValidation() {
    $good = array(
      'sensible-repository',
      'AReasonableName',
      'ACRONYM-project',
      'sol-123',
      '46-helixes',
      'node.io',
      'internet.com',
      'www.internet-site.com.repository',
      'with_under-scores',

      // Can't win them all.
      'A-_._-_._-_._-_._-_._-_._-1',

      // 64-character names are fine.
      str_repeat('a', 64),
    );

    $poor = array(
      '',
      '1',
      '.',
      '-_-',
      'AAAA',
      '..',
      'a/b',
      '../../etc/passwd',
      '/',
      '!',
      '@',
      'ca$hmoney',
      'repo with spaces',
      'hyphen-',
      '-ated',
      '_underscores_',
      'yes!',

      // 65-character names are no good.
      str_repeat('a', 65),
    );

    foreach ($good as $nice_name) {
      $actual = PhabricatorRepository::isValidRepositorySlug($nice_name);
      $this->assertEqual(
        true,
        $actual,
        pht(
          'Expected "%s" to be a valid repository short name.',
          $nice_name));
    }

    foreach ($poor as $poor_name) {
      $actual = PhabricatorRepository::isValidRepositorySlug($poor_name);
      $this->assertEqual(
        false,
        $actual,
        pht(
          'Expected "%s" to be rejected as an invalid repository '.
          'short name.',
          $poor_name));
    }
  }

}
