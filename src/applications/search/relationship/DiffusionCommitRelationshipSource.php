<?php

final class DiffusionCommitRelationshipSource
  extends PhabricatorObjectRelationshipSource {

  public function getResultPHIDTypes() {
    return array(
      PhabricatorRepositoryCommitPHIDType::TYPECONST,
    );
  }

}
