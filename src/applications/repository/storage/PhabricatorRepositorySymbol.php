<?php

/**
 * Records information about symbol locations in a codebase, like where classes
 * and functions are defined.
 *
 * Query symbols with @{class:DiffusionSymbolQuery}.
 *
 * @group diffusion
 */
final class PhabricatorRepositorySymbol extends PhabricatorRepositoryDAO {

  protected $arcanistProjectID;
  protected $symbolContext;
  protected $symbolName;
  protected $symbolType;
  protected $symbolLanguage;
  protected $pathID;
  protected $lineNumber;

  private $path = false;
  private $arcanistProject = false;
  private $repository = false;

  public function getConfiguration() {
    return array(
      self::CONFIG_IDS => self::IDS_MANUAL,
      self::CONFIG_TIMESTAMPS => false,
    ) + parent::getConfiguration();
  }

  public function getURI() {
    if (!$this->repository) {
      // This symbol is in the index, but we don't know which Repository it's
      // part of. Usually this means the Arcanist Project hasn't been linked
      // to a Repository. We can't generate a URI, so just fail.
      return null;
    }

    $request = DiffusionRequest::newFromDictionary(
      array(
        'repository'  => $this->getRepository(),
      ));
    return $request->generateURI(
      array(
        'action'    => 'browse',
        'path'      => $this->getPath(),
        'line'      => $this->getLineNumber(),
      ));
  }

  public function getPath() {
    if ($this->path === false) {
      throw new Exception('Call attachPath() before getPath()!');
    }
    return $this->path;
  }

  public function attachPath($path) {
    $this->path = $path;
    return $this;
  }

  public function getRepository() {
    if ($this->repository === false) {
      throw new Exception('Call attachRepository() before getRepository()!');
    }
    return $this->repository;
  }

  public function attachRepository($repository) {
    $this->repository = $repository;
    return $this;
  }

  public function getArcanistProject() {
    if ($this->arcanistProject === false) {
      throw new Exception(
        'Call attachArcanistProject() before getArcanistProject()!');
    }
    return $this->arcanistProject;
  }

  public function attachArcanistProject($project) {
    $this->arcanistProject = $project;
    return $this;
  }

}
