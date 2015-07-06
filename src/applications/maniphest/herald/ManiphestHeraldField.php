<?php

abstract class ManiphestHeraldField extends HeraldField {

  public function supportsObject($object) {
    return ($object instanceof ManiphestTask);
  }

}
