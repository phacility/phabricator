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
        "Protocol for '{$uri}'.");
    }
  }

  public function testBranchFilter() {
    $git = PhabricatorRepositoryType::REPOSITORY_TYPE_GIT;

    $repo = new PhabricatorRepository();
    $repo->setVersionControlSystem($git);

    $this->assertEqual(
      true,
      $repo->shouldTrackBranch('imaginary'),
      'Track all branches by default.');

    $repo->setDetail(
      'branch-filter',
      array(
        'master' => true,
      ));

    $this->assertEqual(
      true,
      $repo->shouldTrackBranch('master'),
      'Track listed branches.');

    $this->assertEqual(
      false,
      $repo->shouldTrackBranch('imaginary'),
      'Do not track unlisted branches.');
  }

}
