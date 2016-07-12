<?php

abstract class DiffusionExternalSymbolsSource extends Phobject {

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
