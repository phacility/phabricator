<?php

abstract class PhabricatorTypeaheadDatasourceController
  extends PhabricatorController {

  public function getApplicationName() {
    return 'typeahead';
  }

}
