<?php

abstract class HarbormasterBuildStepCustomField
  extends PhabricatorCustomField {

  abstract public function getBuildTargetFieldValue();

}
