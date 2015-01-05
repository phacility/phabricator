<?php

final class DifferentialCommentPreviewController
  extends DifferentialController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $revision = id(new DifferentialRevisionQuery())
      ->setViewer($viewer)
      ->withIDs(array($this->id))
      ->executeOne();
    if (!$revision) {
      return new Aphront404Response();
    }

    $type_comment = PhabricatorTransactions::TYPE_COMMENT;
    $type_action = DifferentialTransaction::TYPE_ACTION;
    $type_edge = PhabricatorTransactions::TYPE_EDGE;
    $type_subscribers = PhabricatorTransactions::TYPE_SUBSCRIBERS;

    $xactions = array();

    $action = $request->getStr('action');
    switch ($action) {
      case DifferentialAction::ACTION_COMMENT:
      case DifferentialAction::ACTION_ADDREVIEWERS:
      case DifferentialAction::ACTION_ADDCCS:
        break;
      default:
        $xactions[] = id(new DifferentialTransaction())
          ->setTransactionType($type_action)
          ->setNewValue($action);
        break;
    }

    $edge_reviewer = DifferentialRevisionHasReviewerEdgeType::EDGECONST;

    $reviewers = $request->getStrList('reviewers');
    if (DifferentialAction::allowReviewers($action) && $reviewers) {
      $faux_edges = array();
      foreach ($reviewers as $phid) {
        $faux_edges[$phid] = array(
          'src' => $revision->getPHID(),
          'type' => $edge_reviewer,
          'dst' => $phid,
        );
      }

      $xactions[] = id(new DifferentialTransaction())
        ->setTransactionType($type_edge)
        ->setMetadataValue('edge:type', $edge_reviewer)
        ->setOldValue(array())
        ->setNewValue($faux_edges);
    }

    $ccs = $request->getStrList('ccs');
    if ($ccs) {
      $xactions[] = id(new DifferentialTransaction())
        ->setTransactionType($type_subscribers)
        ->setOldValue(array())
        ->setNewValue(array_fuse($ccs));
    }

    // Add a comment transaction if there's nothing, so we'll generate a
    // nonempty result.
    if (strlen($request->getStr('content')) || !$xactions) {
      $xactions[] = id(new DifferentialTransaction())
        ->setTransactionType($type_comment)
        ->attachComment(
          id(new ManiphestTransactionComment())
            ->setContent($request->getStr('content')));
    }

    foreach ($xactions as $xaction) {
      $xaction->setAuthorPHID($viewer->getPHID());
    }

    $engine = new PhabricatorMarkupEngine();
    $engine->setViewer($request->getUser());
    foreach ($xactions as $xaction) {
      if ($xaction->hasComment()) {
        $engine->addObject(
          $xaction->getComment(),
          PhabricatorApplicationTransactionComment::MARKUP_FIELD_COMMENT);
      }
    }
    $engine->process();

    $phids = mpull($xactions, 'getRequiredHandlePHIDs');
    $phids = array_mergev($phids);

    $handles = id(new PhabricatorHandleQuery())
      ->setViewer($viewer)
      ->withPHIDs($phids)
      ->execute();

    foreach ($xactions as $xaction) {
      $xaction->setHandles($handles);
    }

    $view = id(new DifferentialTransactionView())
      ->setUser($viewer)
      ->setTransactions($xactions)
      ->setIsPreview(true);

    $metadata = array(
      'reviewers' => $reviewers,
      'ccs' => $ccs,
    );
    if ($action != DifferentialAction::ACTION_COMMENT) {
      $metadata['action'] = $action;
    }

    $draft_key = 'differential-comment-'.$this->id;
    $draft = id(new PhabricatorDraft())
      ->setAuthorPHID($viewer->getPHID())
      ->setDraftKey($draft_key)
      ->setDraft($request->getStr('content'))
      ->setMetadata($metadata)
      ->replaceOrDelete();
    if ($draft->isDeleted()) {
      DifferentialDraft::deleteHasDraft(
        $viewer->getPHID(),
        $revision->getPHID(),
        $draft_key);
    } else {
      DifferentialDraft::markHasDraft(
        $viewer->getPHID(),
        $revision->getPHID(),
        $draft_key);
    }

    return id(new AphrontAjaxResponse())
      ->setContent((string)phutil_implode_html('', $view->buildEvents()));
  }

}
