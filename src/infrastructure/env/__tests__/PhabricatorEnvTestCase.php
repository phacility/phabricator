<?php

final class PhabricatorEnvTestCase extends PhabricatorTestCase {

  public function testLocalWebResource() {
    $map = array(
      '/'                     => true,
      '/D123'                 => true,
      '/path/to/something/'   => true,
      "/path/to/\nHeader: x"  => false,
      'http://evil.com/'      => false,
      '//evil.com/evil/'      => false,
      'javascript:lol'        => false,
      ''                      => false,
      null                    => false,
    );

    foreach ($map as $uri => $expect) {
      $this->assertEqual(
        $expect,
        PhabricatorEnv::isValidLocalWebResource($uri),
        "Valid local resource: {$uri}");
    }
  }

  public function testRemoteWebResource() {
    $map = array(
      'http://example.com/'   => true,
      'derp://example.com/'   => false,
      'javascript:alert(1)'   => false,
    );

    foreach ($map as $uri => $expect) {
      $this->assertEqual(
        $expect,
        PhabricatorEnv::isValidRemoteWebResource($uri),
        "Valid remote resource: {$uri}");
    }
  }

  public function testDictionarySource() {
    $source = new PhabricatorConfigDictionarySource(array('x' => 1));

    $this->assertEqual(
      array(
        'x' => 1,
      ),
      $source->getKeys(array('x', 'z')));

    $source->setKeys(array('z' => 2));

    $this->assertEqual(
      array(
        'x' => 1,
        'z' => 2,
      ),
      $source->getKeys(array('x', 'z')));

    $source->setKeys(array('x' => 3));

    $this->assertEqual(
      array(
        'x' => 3,
        'z' => 2,
      ),
      $source->getKeys(array('x', 'z')));

    $source->deleteKeys(array('x'));

    $this->assertEqual(
      array(
        'z' => 2,
      ),
      $source->getKeys(array('x', 'z')));
  }

  public function testStackSource() {
    $s1 = new PhabricatorConfigDictionarySource(array('x' => 1));
    $s2 = new PhabricatorConfigDictionarySource(array('x' => 2));

    $stack = new PhabricatorConfigStackSource();

    $this->assertEqual(array(), $stack->getKeys(array('x')));

    $stack->pushSource($s1);
    $this->assertEqual(array('x' => 1), $stack->getKeys(array('x')));

    $stack->pushSource($s2);
    $this->assertEqual(array('x' => 2), $stack->getKeys(array('x')));

    $stack->setKeys(array('x' => 3));
    $this->assertEqual(array('x' => 3), $stack->getKeys(array('x')));

    $stack->popSource();
    $this->assertEqual(array('x' => 1), $stack->getKeys(array('x')));

    $stack->popSource();
    $this->assertEqual(array(), $stack->getKeys(array('x')));

    $caught = null;
    try {
      $stack->popSource();
    } catch (Exception $ex) {
      $caught = $ex;
    }

    $this->assertEqual(true, ($caught instanceof Exception));
  }

  public function testOverrides() {
    $outer = PhabricatorEnv::beginScopedEnv();

      $outer->overrideEnvConfig('test.value', 1);
      $this->assertEqual(1, PhabricatorEnv::getEnvConfig('test.value'));

      $inner = PhabricatorEnv::beginScopedEnv();
        $inner->overrideEnvConfig('test.value', 2);
        $this->assertEqual(2, PhabricatorEnv::getEnvConfig('test.value'));
      if (phutil_is_hiphop_runtime()) {
        $inner->__destruct();
      }
      unset($inner);

      $this->assertEqual(1, PhabricatorEnv::getEnvConfig('test.value'));
    if (phutil_is_hiphop_runtime()) {
      $outer->__destruct();
    }
    unset($outer);
  }

  public function testOverrideOrder() {
    $outer = PhabricatorEnv::beginScopedEnv();
    $inner = PhabricatorEnv::beginScopedEnv();

    $caught = null;
    try {
      $outer->__destruct();
    } catch (Exception $ex) {
      $caught = $ex;
    }

    $this->assertEqual(
      true,
      $caught instanceof Exception,
      "Destroying a scoped environment which is not on the top of the stack ".
      "should throw.");

    if (phutil_is_hiphop_runtime()) {
      $inner->__destruct();
    }
    unset($inner);

    if (phutil_is_hiphop_runtime()) {
      $outer->__destruct();
    }
    unset($outer);
  }

  public function testGetEnvExceptions() {
    $caught = null;
    try {
      PhabricatorEnv::getEnvConfig("not.a.real.config.option");
    } catch (Exception $ex) {
      $caught = $ex;
    }
    $this->assertEqual(true, $caught instanceof Exception);

    $caught = null;
    try {
      PhabricatorEnv::getEnvConfig("test.value");
    } catch (Exception $ex) {
      $caught = $ex;
    }
    $this->assertEqual(false, $caught instanceof Exception);
  }

}
