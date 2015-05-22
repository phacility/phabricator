<?php

final class PhabricatorDataNotAttachedException extends Exception {

  public function __construct($object) {
    $stack = debug_backtrace();

    // Shift off `PhabricatorDataNotAttachedException::__construct()`.
    array_shift($stack);
    // Shift off `PhabricatorLiskDAO::assertAttached()`.
    array_shift($stack);

    $frame = head($stack);
    $via = null;
    if (is_array($frame)) {
      $method = idx($frame, 'function');
      if (preg_match('/^get[A-Z]/', $method)) {
        $via = ' '.pht('(via %s)', "{$method}()");
      }
    }

    parent::__construct(
      pht(
        "Attempting to access attached data on %s, but the data is not ".
        "actually attached. Before accessing attachable data on an object, ".
        "you must load and attach it.\n\n".
        "Data is normally attached by calling the corresponding %s method on ".
        "the Query class when the object is loaded. You can also call the ".
        "corresponding %s method explicitly.",
        get_class($object).$via,
        'needX()',
        'attachX()'));
  }

}
