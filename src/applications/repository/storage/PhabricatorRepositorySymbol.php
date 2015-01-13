<?php

/**
 * Records information about symbol locations in a codebase, like where classes
 * and functions are defined.
 *
 * Query symbols with @{class:DiffusionSymbolQuery}.
 */
final class PhabricatorRepositorySymbol extends PhabricatorRepositoryDAO {

  protected $arcanistProjectID;
  protected $symbolContext;
  protected $symbolName;
  protected $symbolType;
  protected $symbolLanguage;
  protected $pathID;
  protected $lineNumber;

  private $path = self::ATTACHABLE;
  private $arcanistProject = self::ATTACHABLE;
  private $repository = self::ATTACHABLE;

  protected function getConfiguration() {
    return array(
      self::CONFIG_IDS => self::IDS_MANUAL,
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
    if (!$this->repository) {
      // This symbol is in the index, but we don't know which Repository it's
      // part of. Usually this means the Arcanist Project hasn't been linked
      // to a Repository. We can't generate a URI, so just fail.
      return null;
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

  public function attachRepository($repository) {
    $this->repository = $repository;
    return $this;
  }

  public function getArcanistProject() {
    return $this->assertAttached($this->arcanistProject);
  }

  public function attachArcanistProject($project) {
    $this->arcanistProject = $project;
    return $this;
  }

}
