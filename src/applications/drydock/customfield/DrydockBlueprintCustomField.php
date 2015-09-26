<?php

abstract class DrydockBlueprintCustomField
  extends PhabricatorCustomField {

  abstract public function getBlueprintFieldValue();

}
