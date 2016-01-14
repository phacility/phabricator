<?php

final class DiffusionRepositoryURIsIndexEngineExtension
  extends PhabricatorIndexEngineExtension {

  const EXTENSIONKEY = 'diffusion.repositories.uri';

  public function getExtensionName() {
    return pht('Repository URIs');
  }

  public function shouldIndexObject($object) {
    return ($object instanceof PhabricatorRepository);
  }

  public function indexObject(
    PhabricatorIndexEngine $engine,
    $object) {
    $object->updateURIIndex();
  }

}
