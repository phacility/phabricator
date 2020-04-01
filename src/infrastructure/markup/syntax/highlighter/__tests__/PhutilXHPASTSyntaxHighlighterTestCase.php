<?php

final class PhutilXHPASTSyntaxHighlighterTestCase extends PhutilTestCase {

  private function highlight($source) {
    $highlighter = new PhutilXHPASTSyntaxHighlighter();
    $future = $highlighter->getHighlightFuture($source);
    return $future->resolve();
  }

  private function read($file) {
    $path = dirname(__FILE__).'/xhpast/'.$file;
    return Filesystem::readFile($path);
  }

  public function testBuiltinClassnames() {
    $this->assertEqual(
      $this->read('builtin-classname.expect'),
      (string)$this->highlight($this->read('builtin-classname.source')),
      pht('Builtin classnames should not be marked as linkable symbols.'));
    $this->assertEqual(
      rtrim($this->read('trailing-comment.expect')),
      (string)$this->highlight($this->read('trailing-comment.source')),
      pht('Trailing comments should not be dropped.'));
    $this->assertEqual(
      $this->read('multiline-token.expect'),
      (string)$this->highlight($this->read('multiline-token.source')),
      pht('Multi-line tokens should be split across lines.'));
    $this->assertEqual(
      $this->read('leading-whitespace.expect'),
      (string)$this->highlight($this->read('leading-whitespace.source')),
      pht('Snippets with leading whitespace should be preserved.'));
    $this->assertEqual(
      $this->read('no-leading-whitespace.expect'),
      (string)$this->highlight($this->read('no-leading-whitespace.source')),
      pht('Snippets with no leading whitespace should be preserved.'));
  }

}
