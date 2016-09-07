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


  final public function getValue(array $request, $key) {
    if (!$this->getExists($request, $key)) {
      return $this->getParameterDefault();
    }

    return $this->getParameterValue($request, $key);
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

  protected function getParameterValue(array $request, $key) {
    return $request[$key];
  }

  protected function getParameterKeys($key) {
    return array($key);
  }

  abstract protected function getParameterTypeName();


  abstract protected function getParameterFormatDescriptions();


  abstract protected function getParameterExamples();

  protected function getParameterDefault() {
    return null;
  }

}
