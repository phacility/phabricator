<?php

final class DifferentialRevisionRelationshipSource
  extends PhabricatorObjectRelationshipSource {

  public function getResultPHIDTypes() {
    return array(
      DifferentialRevisionPHIDType::TYPECONST,
    );
  }

}
