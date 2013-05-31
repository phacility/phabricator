<?php

/**
 * @group conduit
 */
abstract class ConduitAPI_conpherence_Method
  extends ConduitAPIMethod {

  public function getApplication() {
    return PhabricatorApplication::getByClass(
      'PhabricatorApplicationConpherence');
  }

  final protected function getConpherenceURI(ConpherenceThread $conpherence) {
    $id = $conpherence->getID();
    return PhabricatorEnv::getProductionURI(
      $this->getApplication()->getApplicationURI($id));
  }

}
