<?php

abstract class DiffusionExternalSymbolsSource {

  /**
   * @return list of PhabricatorRepositorySymbol
   */
  abstract public function executeQuery(DiffusionExternalSymbolQuery $query);

  protected function buildExternalSymbol() {
    return id(new PhabricatorRepositorySymbol())
      ->setIsExternal(true)
      ->makeEphemeral();
  }
}
