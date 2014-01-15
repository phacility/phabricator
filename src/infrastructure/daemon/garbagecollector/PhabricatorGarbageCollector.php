<?php

abstract class PhabricatorGarbageCollector extends Phobject {

  /**
   * Collect garbage from whatever source this GC handles.
   *
   * @return bool True if there is more garbage to collect.
   */
  abstract public function collectGarbage();

}
