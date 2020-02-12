<?php

final class PhutilJSONFragmentLexerHighlighterTestCase extends PhutilTestCase {

  public function testLexer() {
    $highlighter = id(new PhutilLexerSyntaxHighlighter())
      ->setConfig('language', 'json')
      ->setConfig('lexer', new PhutilJSONFragmentLexer());

    $path = dirname(__FILE__).'/data/jsonfragment/';
    foreach (Filesystem::listDirectory($path, $include_hidden = false) as $f) {
      if (preg_match('/.test$/', $f)) {
        $expect = preg_replace('/.test$/', '.expect', $f);
        $source = Filesystem::readFile($path.'/'.$f);

        $this->assertEqual(
          Filesystem::readFile($path.'/'.$expect),
          (string)$highlighter->getHighlightFuture($source)->resolve(),
          $f);
      }
    }
  }

}
