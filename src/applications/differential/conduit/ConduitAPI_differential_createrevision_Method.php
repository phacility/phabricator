<?php

final class ConduitAPI_differential_createrevision_Method
  extends ConduitAPI_differential_Method {

  public function getMethodDescription() {
    return pht('Create a new Differential revision.');
  }

  public function defineParamTypes() {
    return array(
      // TODO: Arcanist passes this; prevent fatals after D4191 until Conduit
      // version 7 or newer.
      'user'   => 'ignored',
      'diffid' => 'required diffid',
      'fields' => 'required dict',
    );
  }

  public function defineReturnType() {
    return 'nonempty dict';
  }

  public function defineErrorTypes() {
    return array(
      'ERR_BAD_DIFF' => 'Bad diff ID.',
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $viewer = $request->getUser();

    $diff = id(new DifferentialDiffQuery())
      ->setViewer($viewer)
      ->withIDs(array($request->getValue('diffid')))
      ->executeOne();
    if (!$diff) {
      throw new ConduitException('ERR_BAD_DIFF');
    }

    $revision = DifferentialRevision::initializeNewRevision($viewer);
    $revision->attachReviewerStatus(array());

    $this->applyFieldEdit(
      $request,
      $revision,
      $diff,
      $request->getValue('fields', array()),
      $message = null);

    return array(
      'revisionid'  => $revision->getID(),
      'uri'         => PhabricatorEnv::getURI('/D'.$revision->getID()),
    );
  }

}
