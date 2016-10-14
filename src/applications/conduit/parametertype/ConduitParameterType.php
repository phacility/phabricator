<?php

/**
 * Defines how to read a value from a Conduit request.
 *
 * This class behaves like @{class:AphrontHTTPParameterType}, but for Conduit.
 */
abstract class ConduitParameterType extends Phobject {


  private $viewer;


  final public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }


  final public function getViewer() {
    if (!$this->viewer) {
      throw new PhutilInvalidStateException('setViewer');
    }
    return $this->viewer;
  }


  final public function getExists(array $request, $key) {
    return $this->getParameterExists($request, $key);
  }


  final public function getValue(array $request, $key, $strict = true) {
    if (!$this->getExists($request, $key)) {
      return $this->getParameterDefault();
    }

    return $this->getParameterValue($request, $key, $strict);
  }

  final public function getKeys($key) {
    return $this->getParameterKeys($key);
  }

  final public function getDefaultValue() {
    return $this->getParameterDefault();
  }


  final public function getTypeName() {
    return $this->getParameterTypeName();
  }


  final public function getFormatDescriptions() {
    return $this->getParameterFormatDescriptions();
  }


  final public function getExamples() {
    return $this->getParameterExamples();
  }

  protected function raiseValidationException(array $request, $key, $message) {
    // TODO: Specialize this so we can give users more tailored messages from
    // Conduit.
    throw new Exception(
      pht(
        'Error while reading "%s": %s',
        $key,
        $message));
  }


  final public static function getAllTypes() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getTypeName')
      ->setSortMethod('getTypeName')
      ->execute();
  }


  protected function getParameterExists(array $request, $key) {
    return array_key_exists($key, $request);
  }

  protected function getParameterValue(array $request, $key, $strict) {
    return $request[$key];
  }

  protected function getParameterKeys($key) {
    return array($key);
  }

  protected function parseStringValue(array $request, $key, $value, $strict) {
    if (!is_string($value)) {
      $this->raiseValidationException(
        $request,
        $key,
        pht('Expected string, got something else.'));
    }
    return $value;
  }

  protected function parseIntValue(array $request, $key, $value, $strict) {
    if (!$strict && is_string($value) && ctype_digit($value)) {
      $value = $value + 0;
      if (!is_int($value)) {
        $this->raiseValidationException(
          $request,
          $key,
          pht('Integer overflow.'));
      }
    } else if (!is_int($value)) {
      $this->raiseValidationException(
        $request,
        $key,
        pht('Expected integer, got something else.'));
    }
    return $value;
  }

  protected function parseBoolValue(array $request, $key, $value, $strict) {
    $bool_strings = array(
      '0' => false,
      '1' => true,
      'false' => false,
      'true' => true,
    );

    if (!$strict && is_string($value) && isset($bool_strings[$value])) {
      $value = $bool_strings[$value];
    } else if (!is_bool($value)) {
      $this->raiseValidationException(
        $request,
        $key,
        pht('Expected boolean (true or false), got something else.'));
    }
    return $value;
  }

  abstract protected function getParameterTypeName();


  abstract protected function getParameterFormatDescriptions();


  abstract protected function getParameterExamples();

  protected function getParameterDefault() {
    return null;
  }

}
