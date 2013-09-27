<?php

/**
 * @group conduit
 */
final class ConduitAPI_differential_createcomment_Method
  extends ConduitAPIMethod {

  public function getMethodDescription() {
    return "Add a comment to a Differential revision.";
  }

  public function defineParamTypes() {
    return array(
      'revision_id'    => 'required revisionid',
      'message'        => 'optional string',
      'action'         => 'optional string',
      'silent'         => 'optional bool',
      'attach_inlines' => 'optional bool',
    );
  }

  public function defineReturnType() {
    return 'nonempty dict';
  }

  public function defineErrorTypes() {
    return array(
      'ERR_BAD_REVISION' => 'Bad revision ID.',
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $revision = id(new DifferentialRevisionQuery())
      ->setViewer($request->getUser())
      ->withIDs(array($request->getValue('revision_id')))
      ->executeOne();
    if (!$revision) {
      throw new ConduitException('ERR_BAD_REVISION');
    }

    $content_source = PhabricatorContentSource::newForSource(
      PhabricatorContentSource::SOURCE_CONDUIT,
      array());

    $action = $request->getValue('action');
    if (!$action) {
      $action = 'none';
    }

    $editor = new DifferentialCommentEditor(
      $revision,
      $action);
    $editor->setActor($request->getUser());
    $editor->setContentSource($content_source);
    $editor->setMessage($request->getValue('message'));
    $editor->setNoEmail($request->getValue('silent'));
    $editor->setAttachInlineComments($request->getValue('attach_inlines'));
    $editor->save();

    return array(
      'revisionid'  => $revision->getID(),
      'uri'         => PhabricatorEnv::getURI('/D'.$revision->getID()),
    );
  }

}
