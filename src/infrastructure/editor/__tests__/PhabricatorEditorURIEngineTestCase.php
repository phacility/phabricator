<?php

final class PhabricatorEditorURIEngineTestCase
  extends PhabricatorTestCase {

  public function testPatternParsing() {
    $map = array(
      '' => array(),
      '%' => false,
      'aaa%' => false,
      'quack' => array(
        array(
          'type' => 'literal',
          'value' => 'quack',
        ),
      ),
      '%a' => array(
        array(
          'type' => 'variable',
          'value' => 'a',
        ),
      ),
      '%%' => array(
        array(
          'type' => 'variable',
          'value' => '%',
        ),
      ),
      'x%y' => array(
        array(
          'type' => 'literal',
          'value' => 'x',
        ),
        array(
          'type' => 'variable',
          'value' => 'y',
        ),
      ),
      '%xy' => array(
        array(
          'type' => 'variable',
          'value' => 'x',
        ),
        array(
          'type' => 'literal',
          'value' => 'y',
        ),
      ),
      'x%yz' => array(
        array(
          'type' => 'literal',
          'value' => 'x',
        ),
        array(
          'type' => 'variable',
          'value' => 'y',
        ),
        array(
          'type' => 'literal',
          'value' => 'z',
        ),
      ),
    );

    foreach ($map as $input => $expect) {
      try {
        $actual = PhabricatorEditorURIEngine::newPatternTokens($input);
      } catch (Exception $ex) {
        if ($expect !== false) {
          throw $ex;
        }
        $actual = false;
      }

      $this->assertEqual(
        $expect,
        $actual,
        pht('Parse of: %s', $input));
    }
  }

  public function testPatternProtocols() {
    $protocols = array(
      'xyz',
    );
    $protocols = array_fuse($protocols);

    $env = PhabricatorEnv::beginScopedEnv();
    $env->overrideEnvConfig('uri.allowed-editor-protocols', $protocols);

    $map = array(
      'xyz:' => true,
      'xyz:%%' => true,
      'xyz://a' => true,
      'xyz://open/?file=%f' => true,

      '' => false,
      '%%' => false,
      '%r' => false,
      'aaa' => false,
      'xyz%%://' => false,
      'http://' => false,

      // These are fragments that "PhutilURI" can't figure out the protocol
      // for. In theory, they would be safe to allow, they just probably are
      // not very useful.

      'xyz://' => false,
      'xyz://%%' => false,
    );

    foreach ($map as $input => $expect) {
      try {
        id(new PhabricatorEditorURIEngine())
          ->setPattern($input)
          ->validatePattern();

        $actual = true;
      } catch (PhabricatorEditorURIParserException $ex) {
        $actual = false;
      }

      $this->assertEqual(
        $expect,
        $actual,
        pht(
          'Allowed editor "xyz://" template: %s.',
          $input));
    }
  }

}
