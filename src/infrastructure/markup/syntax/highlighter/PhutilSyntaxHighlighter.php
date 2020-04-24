<?php

abstract class PhutilSyntaxHighlighter extends Phobject {
  abstract public function setConfig($key, $value);
  abstract public function getHighlightFuture($source);
}
