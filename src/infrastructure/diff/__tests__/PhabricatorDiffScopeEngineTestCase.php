<?php

final class PhabricatorDiffScopeEngineTestCase
  extends PhabricatorTestCase {

  private $engines = array();

  public function testScopeEngine() {
    $this->assertScopeStart('zebra.c', 4, 2);
  }

  private function assertScopeStart($file, $line, $expect) {
    $engine = $this->getScopeTestEngine($file);

    $actual = $engine->getScopeStart($line);
    $this->assertEqual(
      $expect,
      $actual,
      pht(
        'Expect scope for line %s to start on line %s (actual: %s) in "%s".',
        $line,
        $expect,
        $actual,
        $file));
  }

  private function getScopeTestEngine($file) {
    if (!isset($this->engines[$file])) {
      $this->engines[$file] = $this->newScopeTestEngine($file);
    }

    return $this->engines[$file];
  }

  private function newScopeTestEngine($file) {
    $path = dirname(__FILE__).'/data/'.$file;
    $data = Filesystem::readFile($path);

    $lines = phutil_split_lines($data);
    $map = array();
    foreach ($lines as $key => $line) {
      $map[$key + 1] = $line;
    }

    $engine = id(new PhabricatorDiffScopeEngine())
      ->setLineTextMap($map);

    return $engine;
  }

}
