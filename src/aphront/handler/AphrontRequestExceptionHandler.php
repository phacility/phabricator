<?php

/**
 * React to an unhandled exception escaping request handling in a controller
 * and convert it into a response.
 *
 * These handlers are generally used to render error pages, but they may
 * also perform more specialized handling in situations where an error page
 * is not appropriate.
 */
abstract class AphrontRequestExceptionHandler extends Phobject {

  abstract public function getRequestExceptionHandlerPriority();

  abstract public function canHandleRequestThrowable(
    AphrontRequest $request,
    $throwable);

  abstract public function handleRequestThrowable(
    AphrontRequest $request,
    $throwable);

  final public static function getAllHandlers() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setSortMethod('getRequestExceptionHandlerPriority')
      ->execute();
  }

}
