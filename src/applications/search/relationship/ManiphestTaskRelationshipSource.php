<?php

final class ManiphestTaskRelationshipSource
  extends PhabricatorObjectRelationshipSource {

  public function getResultPHIDTypes() {
    return array(
      ManiphestTaskPHIDType::TYPECONST,
    );
  }

}
