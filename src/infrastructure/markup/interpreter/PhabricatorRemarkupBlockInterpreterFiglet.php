<?php

final class PhabricatorRemarkupBlockInterpreterFiglet
  extends PhutilRemarkupBlockInterpreter {

  public function getInterpreterName() {
    return 'figlet';
  }

  public function markupContent($content, array $argv) {
    if (!Filesystem::binaryExists('figlet')) {
      return $this->markupError(
        pht('Unable to locate the `figlet` binary. Install figlet.'));
    }

    $future = id(new ExecFuture('figlet'))
      ->write(trim($content, "\n"));

    list($err, $stdout, $stderr) = $future->resolve();

    if ($err) {
      return $this->markupError(
        pht(
          'Execution of `figlet` failed:', $stderr));
    }


    if ($this->getEngine()->isTextMode()) {
      return $stdout;
    }

    return phutil_tag(
      'div',
      array(
        'class' => 'PhabricatorMonospaced remarkup-figlet',
      ),
      $stdout);
  }

}
