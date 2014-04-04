<?php

final class PhabricatorRemarkupBlockInterpreterGraphviz
  extends PhutilRemarkupBlockInterpreter {

  public function getInterpreterName() {
    return 'dot';
  }

  public function markupContent($content, array $argv) {
    if (!Filesystem::binaryExists('dot')) {
      return $this->markupError(
        pht('Unable to locate the `dot` binary. Install Graphviz.'));
    }

    $width = $this->parseDimension(idx($argv, 'width'));

    $future = id(new ExecFuture('dot -T%s', 'png'))
      ->setTimeout(15)
      ->write(trim($content));

    list($err, $stdout, $stderr) = $future->resolve();

    if ($err) {
      return $this->markupError(
        pht(
          'Execution of `dot` failed (#%d), check your syntax: %s',
          $err,
          $stderr));
    }

    $file = PhabricatorFile::buildFromFileDataOrHash(
      $stdout,
      array(
        'name' => 'graphviz.png',
      ));

    if ($this->getEngine()->isTextMode()) {
      return '<'.$file->getBestURI().'>';
    }

    return phutil_tag(
      'img',
      array(
        'src' => $file->getBestURI(),
        'width' => nonempty($width, null),
      ));
  }

  // TODO: This is duplicated from PhabricatorRemarkupRuleEmbedFile since they
  // do not share a base class.
  private function parseDimension($string) {
    $string = trim($string);

    if (preg_match('/^(?:\d*\\.)?\d+%?$/', $string)) {
      return $string;
    }

    return null;
  }
}
