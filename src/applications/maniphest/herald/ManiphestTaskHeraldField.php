<?php

abstract class ManiphestTaskHeraldField extends HeraldField {

  public function supportsObject($object) {
    return ($object instanceof ManiphestTask);
  }

}
