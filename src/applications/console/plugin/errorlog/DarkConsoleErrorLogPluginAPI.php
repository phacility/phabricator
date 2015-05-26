<?php

final class DarkConsoleErrorLogPluginAPI {

  private static $errors = array();

  private static $discardMode = false;

  public static function registerErrorHandler() {
    // NOTE: This forces PhutilReadableSerializer to load, so that we are
    // able to handle errors which fire from inside autoloaders (PHP will not
    // reenter autoloaders).
    PhutilReadableSerializer::printableValue(null);
    PhutilErrorHandler::setErrorListener(
      array(__CLASS__, 'handleErrors'));
  }

  public static function enableDiscardMode() {
    self::$discardMode = true;
  }

  public static function disableDiscardMode() {
    self::$discardMode = false;
  }

  public static function getErrors() {
    return self::$errors;
  }

  public static function handleErrors($event, $value, $metadata) {
    if (self::$discardMode) {
      return;
    }

    switch ($event) {
      case PhutilErrorHandler::EXCEPTION:
        // $value is of type Exception
        self::$errors[] = array(
          'details'   => $value->getMessage(),
          'event'     => $event,
          'file'      => $value->getFile(),
          'line'      => $value->getLine(),
          'str'       => $value->getMessage(),
          'trace'     => $metadata['trace'],
        );
        break;
      case PhutilErrorHandler::ERROR:
        // $value is a simple string
        self::$errors[] = array(
          'details'   => $value,
          'event'     => $event,
          'file'      => $metadata['file'],
          'line'      => $metadata['line'],
          'str'       => $value,
          'trace'     => $metadata['trace'],
        );
        break;
      case PhutilErrorHandler::PHLOG:
        // $value can be anything
        self::$errors[] = array(
          'details' => PhutilReadableSerializer::printShallow($value, 3),
          'event'   => $event,
          'file'    => $metadata['file'],
          'line'    => $metadata['line'],
          'str'     => PhutilReadableSerializer::printShort($value),
          'trace'   => $metadata['trace'],
        );
        break;
      default:
        error_log(pht('Unknown event: %s', $event));
        break;
    }
  }

}
