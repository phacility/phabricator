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

  public function shouldLogException(
    AphrontRequest $request,
    Exception $ex) {
    return null;
  }

  abstract public function canHandleRequestException(
    AphrontRequest $request,
    Exception $ex);

  abstract public function handleRequestException(
    AphrontRequest $request,
    Exception $ex);

  final public static function getAllHandlers() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setSortMethod('getRequestExceptionHandlerPriority')
      ->execute();
  }

}
