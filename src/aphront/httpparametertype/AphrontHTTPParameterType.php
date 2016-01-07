<?php

/**
 * Defines how to read a complex value from an HTTP request.
 *
 * Most HTTP parameters are simple (like strings or integers) but some
 * parameters accept more complex values (like lists of users or project names).
 *
 * This class handles reading simple and complex values from a request,
 * performing any required parsing or lookups, and returning a result in a
 * standard format.
 *
 * @task read Reading Values from a Request
 * @task info Information About the Type
 * @task util Parsing Utilities
 * @task impl Implementation
 */
abstract class AphrontHTTPParameterType extends Phobject {


  private $viewer;


/* -(  Reading Values from a Request  )-------------------------------------- */


  /**
   * Set the current viewer.
   *
   * Some parameter types perform complex parsing involving lookups. For
   * example, a type might lookup usernames or project names. These types need
   * to use the current viewer to execute queries.
   *
   * @param PhabricatorUser Current viewer.
   * @return this
   * @task read
   */
  final public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }


  /**
   * Get the current viewer.
   *
   * @return PhabricatorUser Current viewer.
   * @task read
   */
  final public function getViewer() {
    if (!$this->viewer) {
      throw new PhutilInvalidStateException('setViewer');
    }
    return $this->viewer;
  }


  /**
   * Test if a value is present in a request.
   *
   * @param AphrontRequest The incoming request.
   * @param string The key to examine.
   * @return bool True if a readable value is present in the request.
   * @task read
   */
  final public function getExists(AphrontRequest $request, $key) {
    return $this->getParameterExists($request, $key);
  }


  /**
   * Read a value from a request.
   *
   * If the value is not present, a default value is returned (usually `null`).
   * Use @{method:getExists} to test if a value is present.
   *
   * @param AphrontRequest The incoming request.
   * @param string The key to examine.
   * @return wild Value, or default if value is not present.
   * @task read
   */
  final public function getValue(AphrontRequest $request, $key) {

    if (!$this->getExists($request, $key)) {
      return $this->getParameterDefault();
    }

    return $this->getParameterValue($request, $key);
  }


  /**
   * Get the default value for this parameter type.
   *
   * @return wild Default value for this type.
   * @task read
   */
  final public function getDefaultValue() {
    return $this->getParameterDefault();
  }


/* -(  Information About the Type  )----------------------------------------- */


  /**
   * Get a short name for this type, like `string` or `list<phid>`.
   *
   * @return string Short type name.
   * @task info
   */
  final public function getTypeName() {
    return $this->getParameterTypeName();
  }


  /**
   * Get a list of human-readable descriptions of acceptable formats for this
   * type.
   *
   * For example, a type might return strings like these:
   *
   * > Any positive integer.
   * > A comma-separated list of PHIDs.
   *
   * This is used to explain to users how to specify a type when generating
   * documentation.
   *
   * @return list<string> Human-readable list of acceptable formats.
   * @task info
   */
  final public function getFormatDescriptions() {
    return $this->getParameterFormatDescriptions();
  }


  /**
   * Get a list of human-readable examples of how to format this type as an
   * HTTP GET parameter.
   *
   * For example, a type might return strings like these:
   *
   * > v=123
   * > v[]=1&v[]=2
   *
   * This is used to show users how to specify parameters of this type in
   * generated documentation.
   *
   * @return list<string> Human-readable list of format examples.
   * @task info
   */
  final public function getExamples() {
    return $this->getParameterExamples();
  }


/* -(  Utilities  )---------------------------------------------------------- */


  /**
   * Call another type's existence check.
   *
   * This method allows a type to reuse the exitence behavior of a different
   * type. For example, a "list of users" type may have the same basic
   * existence check that a simpler "list of strings" type has, and can just
   * call the simpler type to reuse its behavior.
   *
   * @param AphrontHTTPParameterType The other type.
   * @param AphrontRequest Incoming request.
   * @param string Key to examine.
   * @return bool True if the parameter exists.
   * @task util
   */
  final protected function getExistsWithType(
    AphrontHTTPParameterType $type,
    AphrontRequest $request,
    $key) {

    $type->setViewer($this->getViewer());

    return $type->getParameterExists($request, $key);
  }


  /**
   * Call another type's value parser.
   *
   * This method allows a type to reuse the parsing behavior of a different
   * type. For example, a "list of users" type may start by running the same
   * basic parsing that a simpler "list of strings" type does.
   *
   * @param AphrontHTTPParameterType The other type.
   * @param AphrontRequest Incoming request.
   * @param string Key to examine.
   * @return wild Parsed value.
   * @task util
   */
  final protected function getValueWithType(
    AphrontHTTPParameterType $type,
    AphrontRequest $request,
    $key) {

    $type->setViewer($this->getViewer());

    return $type->getValue($request, $key);
  }


  /**
   * Get a list of all available parameter types.
   *
   * @return list<AphrontHTTPParameterType> List of all available types.
   * @task util
   */
  final public static function getAllTypes() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getTypeName')
      ->setSortMethod('getTypeName')
      ->execute();
  }


/* -(  Implementation  )----------------------------------------------------- */


  /**
   * Test if a parameter exists in a request.
   *
   * See @{method:getExists}. By default, this method tests if the key is
   * present in the request.
   *
   * To call another type's behavior in order to perform this check, use
   * @{method:getExistsWithType}.
   *
   * @param AphrontRequest The incoming request.
   * @param string The key to examine.
   * @return bool True if a readable value is present in the request.
   * @task impl
   */
  protected function getParameterExists(AphrontRequest $request, $key) {
    return $request->getExists($key);
  }


  /**
   * Parse a value from a request.
   *
   * See @{method:getValue}. This method will //only// be called if this type
   * has already asserted that the value exists with
   * @{method:getParameterExists}.
   *
   * To call another type's behavior in order to parse a value, use
   * @{method:getValueWithType}.
   *
   * @param AphrontRequest The incoming request.
   * @param string The key to examine.
   * @return wild Parsed value.
   * @task impl
   */
  abstract protected function getParameterValue(AphrontRequest $request, $key);


  /**
   * Return a simple type name string, like "string" or "list<phid>".
   *
   * See @{method:getTypeName}.
   *
   * @return string Short type name.
   * @task impl
   */
  abstract protected function getParameterTypeName();


  /**
   * Return a human-readable list of format descriptions.
   *
   * See @{method:getFormatDescriptions}.
   *
   * @return list<string> Human-readable list of acceptable formats.
   * @task impl
   */
  abstract protected function getParameterFormatDescriptions();


  /**
   * Return a human-readable list of examples.
   *
   * See @{method:getExamples}.
   *
   * @return list<string> Human-readable list of format examples.
   * @task impl
   */
  abstract protected function getParameterExamples();


  /**
   * Return the default value for this parameter type.
   *
   * See @{method:getDefaultValue}. If unspecified, the default is `null`.
   *
   * @return wild Default value.
   * @task impl
   */
  protected function getParameterDefault() {
    return null;
  }

}
