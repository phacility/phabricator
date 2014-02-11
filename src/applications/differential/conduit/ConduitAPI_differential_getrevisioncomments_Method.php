<?php

final class ConduitAPI_differential_getrevisioncomments_Method
  extends ConduitAPI_differential_Method {

  public function getMethodStatus() {
    return self::METHOD_STATUS_DEPRECATED;
  }

  public function getMethodStatusDescription() {
    return pht('Obsolete and doomed, see T2222.');
  }

  public function getMethodDescription() {
    return "Retrieve Differential Revision Comments.";
  }

  public function defineParamTypes() {
    return array(
      'ids' => 'required list<int>',
      'inlines' => 'optional bool (deprecated)',
    );
  }

  public function defineReturnType() {
    return 'nonempty list<dict<string, wild>>';
  }

  public function defineErrorTypes() {
    return array(
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $results = array();
    $revision_ids = $request->getValue('ids');

    if (!$revision_ids) {
      return $results;
    }

    $revisions = id(new DifferentialRevisionQuery())
      ->setViewer($request->getUser())
      ->withIDs($revision_ids)
      ->execute();

    if (!$revisions) {
      return $results;
    }

    $comments = id(new DifferentialCommentQuery())
      ->withRevisionPHIDs(mpull($revisions, 'getPHID'))
      ->execute();

    $revisions = mpull($revisions, null, 'getPHID');

    foreach ($comments as $comment) {
      $revision = idx($revisions, $comment->getRevisionPHID());
      if (!$revision) {
        continue;
      }

      $result = array(
        'revisionID'  => $revision->getID(),
        'action'      => $comment->getAction(),
        'authorPHID'  => $comment->getAuthorPHID(),
        'dateCreated' => $comment->getDateCreated(),
        'content'     => $comment->getContent(),
      );

      $results[$revision->getID()][] = $result;
    }

    return $results;
  }
}
