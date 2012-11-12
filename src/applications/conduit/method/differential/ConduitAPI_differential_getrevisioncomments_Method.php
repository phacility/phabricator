<?php

/**
 * @group conduit
 */
final class ConduitAPI_differential_getrevisioncomments_Method
  extends ConduitAPI_differential_Method {

  public function getMethodDescription() {
    return "Retrieve Differential Revision Comments.";
  }

  public function defineParamTypes() {
    return array(
      'ids' => 'required list<int>',
      'inlines' => 'optional bool',
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

    $comments = id(new DifferentialComment())->loadAllWhere(
      'revisionID IN (%Ld)',
      $revision_ids);

    $with_inlines = $request->getValue('inlines');
    if ($with_inlines) {
      $inlines = id(new DifferentialInlineComment())->loadAllWhere(
        'revisionID IN (%Ld)',
        $revision_ids);
      $changesets = array();
      if ($inlines) {
        $changesets = id(new DifferentialChangeset())->loadAllWhere(
          'id IN (%Ld)',
          array_unique(mpull($inlines, 'getChangesetID')));
        $inlines = mgroup($inlines, 'getCommentID');
      }
    }

    foreach ($comments as $comment) {
      $revision_id = $comment->getRevisionID();
      $result = array(
        'revisionID'  => $revision_id,
        'action'      => $comment->getAction(),
        'authorPHID'  => $comment->getAuthorPHID(),
        'dateCreated' => $comment->getDateCreated(),
        'content'     => $comment->getContent(),
      );

      if ($with_inlines) {
        $result['inlines'] = array();
        foreach (idx($inlines, $comment->getID(), array()) as $inline) {
          $changeset = idx($changesets, $inline->getChangesetID());
          $result['inlines'][] = $this->buildInlineInfoDictionary(
            $inline,
            $changeset);
        }
        // TODO: Put synthetic inlines without an attached comment somewhere.
      }

      $results[$revision_id][] = $result;
    }

    return $results;
  }
}
