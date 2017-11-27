<?php

final class DiffusionCommandEngineTestCase extends PhabricatorTestCase {

  public function testCommandEngine() {
    $type_git = PhabricatorRepositoryType::REPOSITORY_TYPE_GIT;
    $type_hg = PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL;
    $type_svn = PhabricatorRepositoryType::REPOSITORY_TYPE_SVN;

    $root = dirname(phutil_get_library_root('phabricator'));
    $ssh_wrapper = $root.'/bin/ssh-connect';
    $home = $root.'/support/empty/';


    // Plain commands.

    $this->assertCommandEngineFormat(
      'git xyz',
      array(
        'LANG' => 'en_US.UTF-8',
        'HOME' => $home,
      ),
      array(
        'vcs' => $type_git,
        'argv' => 'xyz',
      ));

    $this->assertCommandEngineFormat(
      (string)csprintf('hg --config ui.ssh=%s xyz', $ssh_wrapper),
      array(
        'LANG' => 'en_US.UTF-8',
        'HGPLAIN' => '1',
      ),
      array(
        'vcs' => $type_hg,
        'argv' => 'xyz',
      ));

    $this->assertCommandEngineFormat(
      'svn --non-interactive xyz',
      array(
        'LANG' => 'en_US.UTF-8',
      ),
      array(
        'vcs' => $type_svn,
        'argv' => 'xyz',
      ));


    // Commands with SSH.

    $this->assertCommandEngineFormat(
      'git xyz',
      array(
        'LANG' => 'en_US.UTF-8',
        'HOME' => $home,
        'GIT_SSH' => $ssh_wrapper,
      ),
      array(
        'vcs' => $type_git,
        'argv' => 'xyz',
        'protocol' => 'ssh',
      ));

    $this->assertCommandEngineFormat(
      (string)csprintf('hg --config ui.ssh=%s xyz', $ssh_wrapper),
      array(
        'LANG' => 'en_US.UTF-8',
        'HGPLAIN' => '1',
      ),
      array(
        'vcs' => $type_hg,
        'argv' => 'xyz',
        'protocol' => 'ssh',
      ));

    $this->assertCommandEngineFormat(
      'svn --non-interactive xyz',
      array(
        'LANG' => 'en_US.UTF-8',
        'SVN_SSH' => $ssh_wrapper,
      ),
      array(
        'vcs' => $type_svn,
        'argv' => 'xyz',
        'protocol' => 'ssh',
      ));


    // Commands with HTTP.

    $this->assertCommandEngineFormat(
      'git xyz',
      array(
        'LANG' => 'en_US.UTF-8',
        'HOME' => $home,
      ),
      array(
        'vcs' => $type_git,
        'argv' => 'xyz',
        'protocol' => 'https',
      ));

    $this->assertCommandEngineFormat(
      (string)csprintf('hg --config ui.ssh=%s xyz', $ssh_wrapper),
      array(
        'LANG' => 'en_US.UTF-8',
        'HGPLAIN' => '1',
      ),
      array(
        'vcs' => $type_hg,
        'argv' => 'xyz',
        'protocol' => 'https',
      ));

    $this->assertCommandEngineFormat(
      'svn --non-interactive --no-auth-cache --trust-server-cert xyz',
      array(
        'LANG' => 'en_US.UTF-8',
      ),
      array(
        'vcs' => $type_svn,
        'argv' => 'xyz',
        'protocol' => 'https',
      ));

    // Test that filtering defenses for "--config" and "--debugger" flag
    // injections in Mercurial are functional. See T13012.

    $caught = null;
    try {
      $this->assertCommandEngineFormat(
        '',
        array(),
        array(
          'vcs' => $type_hg,
          'argv' => '--debugger',
        ));
    } catch (DiffusionMercurialFlagInjectionException $ex) {
      $caught = $ex;
    }

    $this->assertTrue(
      ($caught instanceof DiffusionMercurialFlagInjectionException),
      pht('Expected "--debugger" injection in Mercurial to throw.'));


    $caught = null;
    try {
      $this->assertCommandEngineFormat(
        '',
        array(),
        array(
          'vcs' => $type_hg,
          'argv' => '--config=x',
        ));
    } catch (DiffusionMercurialFlagInjectionException $ex) {
      $caught = $ex;
    }

    $this->assertTrue(
      ($caught instanceof DiffusionMercurialFlagInjectionException),
      pht('Expected "--config" injection in Mercurial to throw.'));

    $caught = null;
    try {
      $this->assertCommandEngineFormat(
        '',
        array(),
        array(
          'vcs' => $type_hg,
          'argv' => (string)csprintf('%s', '--config=x'),
        ));
    } catch (DiffusionMercurialFlagInjectionException $ex) {
      $caught = $ex;
    }

    $this->assertTrue(
      ($caught instanceof DiffusionMercurialFlagInjectionException),
      pht('Expected quoted "--config" injection in Mercurial to throw.'));

  }

  private function assertCommandEngineFormat(
    $command,
    array $env,
    array $inputs) {

    $repository = id(new PhabricatorRepository())
      ->setVersionControlSystem($inputs['vcs']);

    $future = DiffusionCommandEngine::newCommandEngine($repository)
      ->setArgv((array)$inputs['argv'])
      ->setProtocol(idx($inputs, 'protocol'))
      ->newFuture();

    $command_string = $future->getCommand();

    $actual_command = $command_string->getUnmaskedString();
    $this->assertEqual($command, $actual_command);

    $actual_environment = $future->getEnv();

    $compare_environment = array_select_keys(
      $actual_environment,
      array_keys($env));

    $this->assertEqual($env, $compare_environment);
  }

}
