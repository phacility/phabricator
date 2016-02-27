<?php

final class PhabricatorAuditAddCommentController
  extends PhabricatorAuditController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    if (!$request->isFormPost()) {
      return new Aphront403Response();
    }

    $commit_phid = $request->getStr('commit');
    $commit = id(new DiffusionCommitQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($commit_phid))
      ->needAuditRequests(true)
      ->executeOne();
    if (!$commit) {
      return new Aphront404Response();
    }

    $xactions = array();

    // make sure we only add auditors or ccs if the action matches
    $action = $request->getStr('action');
    switch ($action) {
      case PhabricatorAuditActionConstants::ADD_AUDITORS:
        $auditors = $request->getArr('auditors');
        $xactions[] = id(new PhabricatorAuditTransaction())
          ->setTransactionType(PhabricatorAuditActionConstants::ADD_AUDITORS)
          ->setNewValue(array_fuse($auditors));
        break;
      case PhabricatorAuditActionConstants::ADD_CCS:
        $xactions[] = id(new PhabricatorAuditTransaction())
          ->setTransactionType(PhabricatorTransactions::TYPE_SUBSCRIBERS)
          ->setNewValue(
            array(
              '+' => $request->getArr('ccs'),
            ));
        break;
      case PhabricatorAuditActionConstants::COMMENT:
        // We'll deal with this below.
        break;
      default:
        $xactions[] = id(new PhabricatorAuditTransaction())
          ->setTransactionType(PhabricatorAuditActionConstants::ACTION)
          ->setNewValue($action);
        break;
    }

    $content = $request->getStr('content');
    if (strlen($content)) {
      $xactions[] = id(new PhabricatorAuditTransaction())
        ->setTransactionType(PhabricatorTransactions::TYPE_COMMENT)
        ->attachComment(
          id(new PhabricatorAuditTransactionComment())
            ->setCommitPHID($commit->getPHID())
            ->setContent($content));
    }

    $inlines = PhabricatorAuditInlineComment::loadDraftComments(
      $viewer,
      $commit->getPHID());
    foreach ($inlines as $inline) {
      $xactions[] = id(new PhabricatorAuditTransaction())
        ->setTransactionType(PhabricatorAuditActionConstants::INLINE)
        ->attachComment($inline->getTransactionComment());
    }

    id(new PhabricatorAuditEditor())
      ->setActor($viewer)
      ->setContentSourceFromRequest($request)
      ->setContinueOnMissingFields(true)
      ->applyTransactions($commit, $xactions);

    $draft = id(new PhabricatorDraft())->loadOneWhere(
      'authorPHID = %s AND draftKey = %s',
      $viewer->getPHID(),
      'diffusion-audit-'.$commit->getID());
    if ($draft) {
      $draft->delete();
    }

    $uri = $commit->getURI();

    return id(new AphrontRedirectResponse())->setURI($uri);
  }

}
