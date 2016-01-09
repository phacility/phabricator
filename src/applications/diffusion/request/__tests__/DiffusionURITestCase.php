<?php

final class DiffusionURITestCase extends PhutilTestCase {

  public function testBlobDecode() {
    $map = array(
      // This is a basic blob.
      'branch/path.ext;abc$3' => array(
        'branch'  => 'branch',
        'path'    => 'path.ext',
        'commit'  => 'abc',
        'line'    => '3',
      ),
      'branch/path.ext$3' => array(
        'branch'  => 'branch',
        'path'    => 'path.ext',
        'line'    => '3',
      ),
      'branch/money;;/$$100'  => array(
        'branch'  => 'branch',
        'path'    => 'money;/$100',
      ),
      'a%252Fb/' => array(
        'branch'  => 'a/b',
      ),
      'branch/path/;Version-1_0_0' => array(
        'branch' => 'branch',
        'path'   => 'path/',
        'commit' => 'Version-1_0_0',
      ),
      'branch/path/;$$moneytag$$' => array(
        'branch' => 'branch',
        'path'   => 'path/',
        'commit' => '$moneytag$',
      ),
      'branch/path/semicolon;;;;;$$;;semicolon;;$$$$$100' => array(
        'branch' => 'branch',
        'path'   => 'path/semicolon;;',
        'commit' => '$;;semicolon;;$$',
        'line'   => '100',
      ),
      'branch/path.ext;abc$3-5,7-12,14' => array(
        'branch'  => 'branch',
        'path'    => 'path.ext',
        'commit'  => 'abc',
        'line'    => '3-5,7-12,14',
      ),
    );

    foreach ($map as $input => $expect) {

      // Simulate decode effect of the webserver.
      $input = rawurldecode($input);

      $expect = $expect + array(
        'branch' => null,
        'path'   => null,
        'commit' => null,
        'line'   => null,
      );
      $expect = array_select_keys(
        $expect,
        array('branch', 'path', 'commit', 'line'));

      $actual = $this->parseBlob($input);

      $this->assertEqual(
        $expect,
        $actual,
        pht("Parsing '%s'", $input));
    }
  }

  public function testBlobDecodeFail() {
    $this->tryTestCaseMap(
      array(
        'branch/path/../../../secrets/secrets.key' => false,
      ),
      array($this, 'parseBlob'));
  }

  public function parseBlob($blob) {
    return DiffusionRequest::parseRequestBlob(
      $blob,
      $supports_branches = true);
  }

  public function testURIGeneration() {
    $actor = PhabricatorUser::getOmnipotentUser();

    $repository = PhabricatorRepository::initializeNewRepository($actor)
      ->setCallsign('A')
      ->makeEphemeral();

    $map = array(
      '/diffusion/A/browse/branch/path.ext;abc$1' => array(
        'action'    => 'browse',
        'branch'    => 'branch',
        'path'      => 'path.ext',
        'commit'    => 'abc',
        'line'      => '1',
      ),
      '/diffusion/A/browse/a%252Fb/path.ext' => array(
        'action'    => 'browse',
        'branch'    => 'a/b',
        'path'      => 'path.ext',
      ),
      '/diffusion/A/browse/%2B/%20%21' => array(
        'action'    => 'browse',
        'path'      => '+/ !',
      ),
      '/diffusion/A/browse/money/%24%24100$2' => array(
        'action'    => 'browse',
        'path'      => 'money/$100',
        'line'      => '2',
      ),
      '/diffusion/A/browse/path/to/file.ext?view=things' => array(
        'action'    => 'browse',
        'path'      => 'path/to/file.ext',
        'params'    => array(
          'view' => 'things',
        ),
      ),
      '/diffusion/A/repository/master/' => array(
        'action'    => 'branch',
        'branch'    => 'master',
      ),
      'path/to/file.ext;abc' => array(
        'action'    => 'rendering-ref',
        'path'      => 'path/to/file.ext',
        'commit'    => 'abc',
      ),
      '/diffusion/A/browse/branch/path.ext$3-5%2C7-12%2C14' => array(
        'action'    => 'browse',
        'branch'    => 'branch',
        'path'      => 'path.ext',
        'line'      => '3-5,7-12,14',
      ),
    );

    foreach ($map as $expect => $input) {
      $actual = $repository->generateURI($input);
      $this->assertEqual($expect, (string)$actual);
    }
  }

}
