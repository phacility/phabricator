<?php

final class DifferentialUpdateRevisionConduitAPIMethod
  extends DifferentialConduitAPIMethod {

  public function getAPIMethodName() {
    return 'differential.updaterevision';
  }

  public function getMethodDescription() {
    return pht('Update a Differential revision.');
  }

  public function getMethodStatus() {
    return self::METHOD_STATUS_FROZEN;
  }

  public function getMethodStatusDescription() {
    return pht(
      'This method is frozen and will eventually be deprecated. New code '.
      'should use "differential.revision.edit" instead.');
  }

  protected function defineParamTypes() {
    return array(
      'id'        => 'required revisionid',
      'diffid'    => 'required diffid',
      'fields'    => 'required dict',
      'message'   => 'required string',
    );
  }

  protected function defineReturnType() {
    return 'nonempty dict';
  }

  protected function defineErrorTypes() {
    return array(
      'ERR_BAD_DIFF'     => pht('Bad diff ID.'),
      'ERR_BAD_REVISION' => pht('Bad revision ID.'),
      'ERR_WRONG_USER'   => pht('You are not the author of this revision.'),
      'ERR_CLOSED'       => pht('This revision has already been closed.'),
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

    $revision = id(new DifferentialRevisionQuery())
      ->setViewer($request->getUser())
      ->withIDs(array($request->getValue('id')))
      ->needReviewers(true)
      ->needActiveDiffs(true)
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$revision) {
      throw new ConduitException('ERR_BAD_REVISION');
    }

    if ($revision->isPublished()) {
      throw new ConduitException('ERR_CLOSED');
    }

    $this->applyFieldEdit(
      $request,
      $revision,
      $diff,
      $request->getValue('fields', array()),
      $request->getValue('message'));

    return array(
      'revisionid'  => $revision->getID(),
      'uri'         => PhabricatorEnv::getURI('/D'.$revision->getID()),
    );
  }

}
