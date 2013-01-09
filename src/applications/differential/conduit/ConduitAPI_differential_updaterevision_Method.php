<?php

/**
 * @group conduit
 */
final class ConduitAPI_differential_updaterevision_Method
  extends ConduitAPIMethod {

  public function getMethodDescription() {
    return "Update a Differential revision.";
  }

  public function defineParamTypes() {
    return array(
      'id'        => 'required revisionid',
      'diffid'    => 'required diffid',
      'fields'    => 'required dict',
      'message'   => 'required string',
    );
  }

  public function defineReturnType() {
    return 'nonempty dict';
  }

  public function defineErrorTypes() {
    return array(
      'ERR_BAD_DIFF' => 'Bad diff ID.',
      'ERR_BAD_REVISION' => 'Bad revision ID.',
      'ERR_WRONG_USER' => 'You are not the author of this revision.',
      'ERR_CLOSED' => 'This revision has already been closed.',
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $diff = id(new DifferentialDiff())->load($request->getValue('diffid'));
    if (!$diff) {
      throw new ConduitException('ERR_BAD_DIFF');
    }

    $revision = id(new DifferentialRevision())->load($request->getValue('id'));
    if (!$revision) {
      throw new ConduitException('ERR_BAD_REVISION');
    }

    if ($request->getUser()->getPHID() !== $revision->getAuthorPHID()) {
      throw new ConduitException('ERR_WRONG_USER');
    }

    if ($revision->getStatus() == ArcanistDifferentialRevisionStatus::CLOSED) {
      throw new ConduitException('ERR_CLOSED');
    }

    $content_source = PhabricatorContentSource::newForSource(
      PhabricatorContentSource::SOURCE_CONDUIT,
      array());

    $editor = new DifferentialRevisionEditor(
      $revision);
    $editor->setActor($request->getUser());
    $editor->setContentSource($content_source);
    $fields = $request->getValue('fields');
    $editor->copyFieldsFromConduit($fields);

    $editor->addDiff($diff, $request->getValue('message'));
    $editor->save();

    return array(
      'revisionid'  => $revision->getID(),
      'uri'         => PhabricatorEnv::getURI('/D'.$revision->getID()),
    );
  }

}
