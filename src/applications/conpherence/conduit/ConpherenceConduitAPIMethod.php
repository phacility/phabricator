<?php

abstract class ConpherenceConduitAPIMethod extends ConduitAPIMethod {

  final public function getApplication() {
    return PhabricatorApplication::getByClass(
      'PhabricatorConpherenceApplication');
  }

  final protected function getConpherenceURI(ConpherenceThread $conpherence) {
    $id = $conpherence->getID();
    return PhabricatorEnv::getProductionURI(
      $this->getApplication()->getApplicationURI($id));
  }

}
