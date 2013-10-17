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

    $future = id(new ExecFuture('dot -T%s', 'png'))
      ->write(trim($content));

    list($err, $stdout, $stderr) = $future->resolve();

    if ($err) {
      return $this->markupError(
        pht(
          'Execution of `dot` failed, check your syntax: %s', $stderr));
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
      ));
  }

}
