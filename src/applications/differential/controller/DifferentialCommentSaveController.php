<?php

final class DifferentialCommentSaveController
  extends DifferentialController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    if (!$request->isFormPost()) {
      return new Aphront400Response();
    }

    $revision = id(new DifferentialRevisionQuery())
      ->setViewer($viewer)
      ->withIDs(array($this->id))
      ->needReviewerStatus(true)
      ->needReviewerAuthority(true)
      ->executeOne();
    if (!$revision) {
      return new Aphront404Response();
    }

    $type_action = DifferentialTransaction::TYPE_ACTION;
    $type_subscribers = PhabricatorTransactions::TYPE_SUBSCRIBERS;
    $type_edge = PhabricatorTransactions::TYPE_EDGE;
    $type_comment = PhabricatorTransactions::TYPE_COMMENT;
    $type_inline = DifferentialTransaction::TYPE_INLINE;

    $edge_reviewer = DifferentialRevisionHasReviewerEdgeType::EDGECONST;

    $xactions = array();

    $action = $request->getStr('action');
    switch ($action) {
      case DifferentialAction::ACTION_COMMENT:
      case DifferentialAction::ACTION_ADDREVIEWERS:
      case DifferentialAction::ACTION_ADDCCS:
        // These transaction types have no direct effect, they just
        // accompany other transaction types which can have an effect.
        break;
      default:
        $xactions[] = id(new DifferentialTransaction())
          ->setTransactionType($type_action)
          ->setNewValue($request->getStr('action'));
        break;
    }

    $ccs = $request->getArr('ccs');
    if ($ccs) {
      $xactions[] = id(new DifferentialTransaction())
        ->setTransactionType($type_subscribers)
        ->setNewValue(array('+' => $ccs));
    }

    $current_reviewers = mpull(
      $revision->getReviewerStatus(),
      null,
      'getReviewerPHID');

    $reviewer_edges = array();
    $add_reviewers = $request->getArr('reviewers');
    foreach ($add_reviewers as $reviewer_phid) {
      if (isset($current_reviewers[$reviewer_phid])) {
        continue;
      }
      $reviewer = new DifferentialReviewer(
        $reviewer_phid,
        array(
          'status' => DifferentialReviewerStatus::STATUS_ADDED,
        ));
      $reviewer_edges[$reviewer_phid] = array(
        'data' => $reviewer->getEdgeData(),
      );
    }

    if ($add_reviewers) {
      $xactions[] = id(new DifferentialTransaction())
        ->setTransactionType($type_edge)
        ->setMetadataValue('edge:type', $edge_reviewer)
        ->setNewValue(array('+' => $reviewer_edges));
    }

    $inlines = DifferentialTransactionQuery::loadUnsubmittedInlineComments(
      $viewer,
      $revision);
    foreach ($inlines as $inline) {
      $xactions[] = id(new DifferentialTransaction())
        ->setTransactionType($type_inline)
        ->attachComment($inline);
    }

    // NOTE: If there are no other transactions, add an empty comment
    // transaction so that we'll raise a more user-friendly error message,
    // to the effect of "you can not post an empty comment".
    $no_xactions = !$xactions;

    $comment = $request->getStr('comment');
    if (strlen($comment) || $no_xactions) {
      $xactions[] = id(new DifferentialTransaction())
        ->setTransactionType($type_comment)
        ->attachComment(
          id(new DifferentialTransactionComment())
            ->setRevisionPHID($revision->getPHID())
            ->setContent($comment));
    }


    $editor = id(new DifferentialTransactionEditor())
      ->setActor($viewer)
      ->setContentSourceFromRequest($request)
      ->setContinueOnMissingFields(true)
      ->setContinueOnNoEffect($request->isContinueRequest());

    $revision_uri = '/D'.$revision->getID();

    try {
      $editor->applyTransactions($revision, $xactions);
    } catch (PhabricatorApplicationTransactionNoEffectException $ex) {
      return id(new PhabricatorApplicationTransactionNoEffectResponse())
        ->setCancelURI($revision_uri)
        ->setException($ex);
    } catch (PhabricatorApplicationTransactionValidationException $ex) {
      return id(new PhabricatorApplicationTransactionValidationResponse())
        ->setCancelURI($revision_uri)
        ->setException($ex);
    }

    $user = $request->getUser();
    $draft = id(new PhabricatorDraft())->loadOneWhere(
      'authorPHID = %s AND draftKey = %s',
      $user->getPHID(),
      'differential-comment-'.$revision->getID());
    if ($draft) {
      $draft->delete();
    }
    DifferentialDraft::deleteAllDrafts($user->getPHID(), $revision->getPHID());

    return id(new AphrontRedirectResponse())
      ->setURI('/D'.$revision->getID());
  }

}
