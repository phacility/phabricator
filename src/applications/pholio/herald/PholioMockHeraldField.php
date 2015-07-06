<?php

abstract class PholioMockHeraldField extends HeraldField {

  public function supportsObject($object) {
    return ($object instanceof PholioMock);
  }

}
