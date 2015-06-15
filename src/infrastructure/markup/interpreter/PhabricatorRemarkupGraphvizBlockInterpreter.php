<?php

final class PhabricatorRemarkupGraphvizBlockInterpreter
  extends PhutilRemarkupBlockInterpreter {

  public function getInterpreterName() {
    return 'dot';
  }

  public function markupContent($content, array $argv) {
    if (!Filesystem::binaryExists('dot')) {
      return $this->markupError(
        pht(
          'Unable to locate the `%s` binary. Install Graphviz.',
          'dot'));
    }

    $width = $this->parseDimension(idx($argv, 'width'));

    $future = id(new ExecFuture('dot -T%s', 'png'))
      ->setTimeout(15)
      ->write(trim($content));

    list($err, $stdout, $stderr) = $future->resolve();

    if ($err) {
      return $this->markupError(
        pht(
          'Execution of `%s` failed (#%d), check your syntax: %s',
          'dot',
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

  // TODO: This is duplicated from PhabricatorEmbedFileRemarkupRule since they
  // do not share a base class.
  private function parseDimension($string) {
    $string = trim($string);

    if (preg_match('/^(?:\d*\\.)?\d+%?$/', $string)) {
      return $string;
    }

    return null;
  }
}
