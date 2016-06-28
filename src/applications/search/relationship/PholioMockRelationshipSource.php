<?php

final class PholioMockRelationshipSource
  extends PhabricatorObjectRelationshipSource {

  public function getResultPHIDTypes() {
    return array(
      PholioMockPHIDType::TYPECONST,
    );
  }

}
