<?php

final class PhabricatorAuditPreviewController
  extends PhabricatorAuditController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $commit = id(new PhabricatorRepositoryCommit())->load($id);
    if (!$commit) {
      return new Aphront404Response();
    }

    $xactions = array();

    $action = $request->getStr('action');
    if ($action != PhabricatorAuditActionConstants::COMMENT) {
      $action_xaction = id(new PhabricatorAuditTransaction())
        ->setAuthorPHID($viewer->getPHID())
        ->setObjectPHID($commit->getPHID())
        ->setTransactionType(PhabricatorAuditActionConstants::ACTION)
        ->setNewValue($action);

      $auditors = $request->getStrList('auditors');
      if ($action == PhabricatorAuditActionConstants::ADD_AUDITORS &&
        $auditors) {
        $action_xaction->setTransactionType($action);
        $action_xaction->setNewValue(array_fuse($auditors));
      }

      $ccs = $request->getStrList('ccs');
      if ($action == PhabricatorAuditActionConstants::ADD_CCS && $ccs) {
        $action_xaction->setTransactionType(
          PhabricatorTransactions::TYPE_SUBSCRIBERS);

        // NOTE: This doesn't get processed before use, so just provide fake
        // values.
        $action_xaction->setOldValue(array());
        $action_xaction->setNewValue($ccs);
      }

      $xactions[] = $action_xaction;
    }

    $content = $request->getStr('content');
    if (strlen($content)) {
      $xactions[] = id(new PhabricatorAuditTransaction())
        ->setAuthorPHID($viewer->getPHID())
        ->setObjectPHID($commit->getPHID())
        ->setTransactionType(PhabricatorTransactions::TYPE_COMMENT)
        ->attachComment(
          id(new PhabricatorAuditTransactionComment())
            ->setContent($content));
    }

    $phids = array();
    foreach ($xactions as $xaction) {
      $phids[] = $xaction->getRequiredHandlePHIDs();
    }
    $phids = array_mergev($phids);
    $handles = $this->loadViewerHandles($phids);
    foreach ($xactions as $xaction) {
      $xaction->setHandles($handles);
    }

    $view = id(new PhabricatorAuditTransactionView())
      ->setIsPreview(true)
      ->setUser($viewer)
      ->setObjectPHID($commit->getPHID())
      ->setTransactions($xactions);

    id(new PhabricatorDraft())
      ->setAuthorPHID($viewer->getPHID())
      ->setDraftKey('diffusion-audit-'.$id)
      ->setDraft($content)
      ->replaceOrDelete();

    return id(new AphrontAjaxResponse())->setContent(hsprintf('%s', $view));
  }

}
