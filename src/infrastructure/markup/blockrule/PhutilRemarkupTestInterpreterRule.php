<?php

final class PhutilRemarkupTestInterpreterRule
  extends PhutilRemarkupBlockInterpreter {

  public function getInterpreterName() {
    return 'phutil_test_block_interpreter';
  }

  public function markupContent($content, array $argv) {
    return sprintf(
      "Content: (%s)\nArgv: (%s)",
      $content,
      phutil_build_http_querystring($argv));
  }

}
