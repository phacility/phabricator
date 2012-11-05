<?php

/**
 * @group conduit
 * @deprecated
 */
final class ConduitAPI_differential_markcommitted_Method
  extends ConduitAPIMethod {

  public function getMethodStatus() {
    return self::METHOD_STATUS_DEPRECATED;
  }

  public function getMethodStatusDescription() {
    return "Replaced by 'differential.close'.";
  }

  public function getMethodDescription() {
    return "Mark a revision closed.";
  }

  public function defineParamTypes() {
    return array(
      'revision_id' => 'required revision_id',
    );
  }

  public function defineReturnType() {
    return 'void';
  }

  public function defineErrorTypes() {
    return array(
      'ERR_NOT_FOUND' => 'Revision was not found.',
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $id = $request->getValue('revision_id');

    $revision = id(new DifferentialRevision())->load($id);
    if (!$revision) {
      throw new ConduitException('ERR_NOT_FOUND');
    }

    if ($revision->getStatus() == ArcanistDifferentialRevisionStatus::CLOSED) {
      return;
    }

    $revision->loadRelationships();

    $editor = new DifferentialCommentEditor(
      $revision,
      DifferentialAction::ACTION_CLOSE);
    $editor->setActor($request->getUser());
    $editor->save();
  }

}
