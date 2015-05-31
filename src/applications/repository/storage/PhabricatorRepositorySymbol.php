<?php

/**
 * Records information about symbol locations in a codebase, like where classes
 * and functions are defined.
 *
 * Query symbols with @{class:DiffusionSymbolQuery}.
 */
final class PhabricatorRepositorySymbol extends PhabricatorRepositoryDAO {

  protected $repositoryPHID;
  protected $symbolContext;
  protected $symbolName;
  protected $symbolType;
  protected $symbolLanguage;
  protected $pathID;
  protected $lineNumber;
  private $isExternal;
  private $source;
  private $location;
  private $externalURI;

  private $path = self::ATTACHABLE;
  private $repository = self::ATTACHABLE;

  protected function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_COLUMN_SCHEMA => array(
        'id' => null,
        'symbolContext' => 'text128',
        'symbolName' => 'text128',
        'symbolType' => 'text12',
        'symbolLanguage' => 'text32',
        'lineNumber' => 'uint32',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'PRIMARY' => null,
        'symbolName' => array(
          'columns' => array('symbolName'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function getURI() {
    if ($this->isExternal) {
      return $this->externalURI;
    }

    $request = DiffusionRequest::newFromDictionary(
      array(
        'user' => PhabricatorUser::getOmnipotentUser(),
        'repository' => $this->getRepository(),
      ));
    return $request->generateURI(
      array(
        'action'    => 'browse',
        'path'      => $this->getPath(),
        'line'      => $this->getLineNumber(),
      ));
  }

  public function getPath() {
    return $this->assertAttached($this->path);
  }

  public function attachPath($path) {
    $this->path = $path;
    return $this;
  }

  public function getRepository() {
    return $this->assertAttached($this->repository);
  }

  public function attachRepository(PhabricatorRepository $repository) {
    $this->repository = $repository;
    return $this;
  }

  public function isExternal() {
    return $this->isExternal;
  }
  public function setIsExternal($is_external) {
    $this->isExternal = $is_external;
    return $this;
  }

  public function getSource() {
    return $this->source;
  }
  public function setSource($source) {
    $this->source = $source;
    return $this;
  }

  public function getLocation() {
    return $this->location;
  }
  public function setLocation($location) {
    $this->location = $location;
    return $this;
  }

  public function setExternalURI($external_uri) {
    $this->externalURI = $external_uri;
    return $this;
  }
}
