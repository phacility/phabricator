<?php

final class PhutilPHPFragmentLexerHighlighterTestCase extends PhutilTestCase {

  public function testLexer() {
    $highlighter = new PhutilLexerSyntaxHighlighter();
    $highlighter->setConfig('language', 'php');
    $highlighter->setConfig('lexer', new PhutilPHPFragmentLexer());


    $path = dirname(__FILE__).'/phpfragment/';
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
