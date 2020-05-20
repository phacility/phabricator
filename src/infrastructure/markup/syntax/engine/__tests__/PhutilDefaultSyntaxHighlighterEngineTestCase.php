<?php

/**
 * Test cases for @{class:PhutilDefaultSyntaxHighlighterEngine}.
 */
final class PhutilDefaultSyntaxHighlighterEngineTestCase
  extends PhutilTestCase {

  public function testFilenameGreediness() {
    $names = array(
      'x.php'       => 'php',
      '/x.php'      => 'php',
      'x.y.php'     => 'php',
      '/x.y/z.php'  => 'php',
      '/x.php/'     => null,
    );

    $engine = new PhutilDefaultSyntaxHighlighterEngine();
    foreach ($names as $path => $language) {
      $detect = $engine->getLanguageFromFilename($path);
      $this->assertEqual(
        $language,
        $detect,
        pht('Language detect for %s', $path));
    }
  }

}
