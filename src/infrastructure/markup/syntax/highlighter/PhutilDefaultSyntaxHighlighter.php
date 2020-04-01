<?php

final class PhutilDefaultSyntaxHighlighter extends Phobject {

  public function setConfig($key, $value) {
    return $this;
  }

  public function getHighlightFuture($source) {
    $result = hsprintf('%s', $source);
    return new ImmediateFuture($result);
  }

}
