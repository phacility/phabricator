<?php

/**
 * @group conduit
 */
final class ConduitAPI_differential_createrevision_Method
  extends ConduitAPIMethod {

  public function getMethodDescription() {
    return "Create a new Differential revision.";
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
    $fields = $request->getValue('fields');

    $diff = id(new DifferentialDiff())->load($request->getValue('diffid'));
    if (!$diff) {
      throw new ConduitException('ERR_BAD_DIFF');
    }

    $revision = DifferentialRevisionEditor::newRevisionFromConduitWithDiff(
      $fields,
      $diff,
      $request->getUser());

    return array(
      'revisionid'  => $revision->getID(),
      'uri'         => PhabricatorEnv::getURI('/D'.$revision->getID()),
    );
  }

}
