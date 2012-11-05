<?php

/**
 * @group search
 */
abstract class PhabricatorSearchEngineSelector {

  final public function __construct() {
    // <empty>
  }

  abstract public function newEngine();

  final public static function newSelector() {
    return PhabricatorEnv::newObjectFromConfig('search.engine-selector');
  }

}
