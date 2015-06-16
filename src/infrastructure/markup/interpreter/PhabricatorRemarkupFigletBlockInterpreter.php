<?php

final class PhabricatorRemarkupFigletBlockInterpreter
  extends PhutilRemarkupBlockInterpreter {

  public function getInterpreterName() {
    return 'figlet';
  }

  public function markupContent($content, array $argv) {
    if (!Filesystem::binaryExists('figlet')) {
      return $this->markupError(
        pht(
          'Unable to locate the `%s` binary. Install figlet.',
          'figlet'));
    }

    $font = idx($argv, 'font', 'standard');
    $safe_font = preg_replace('/[^0-9a-zA-Z-_.]/', '', $font);
    $future = id(new ExecFuture('figlet -f %s', $safe_font))
      ->setTimeout(15)
      ->write(trim($content, "\n"));

    list($err, $stdout, $stderr) = $future->resolve();

    if ($err) {
      return $this->markupError(
        pht(
          'Execution of `%s` failed: %s',
          'figlet',
          $stderr));
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
