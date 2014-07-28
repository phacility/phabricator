<?php

final class PhabricatorAuditAddCommentController
  extends PhabricatorAuditController {

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    if (!$request->isFormPost()) {
      return new Aphront403Response();
    }

    $commit_phid = $request->getStr('commit');
    $commit = id(new PhabricatorRepositoryCommit())->loadOneWhere(
      'phid = %s',
      $commit_phid);
    if (!$commit) {
      return new Aphront404Response();
    }

    $phids = array($commit_phid);

    $comments = array();

    // make sure we only add auditors or ccs if the action matches
    $action = $request->getStr('action');
    switch ($action) {
      case PhabricatorAuditActionConstants::ADD_AUDITORS:
        $auditors = $request->getArr('auditors');
        $comments[] = id(new PhabricatorAuditComment())
          ->setAction(PhabricatorAuditActionConstants::ADD_AUDITORS)
          ->setMetadata(
            array(
              PhabricatorAuditComment::METADATA_ADDED_AUDITORS => $auditors,
            ));
        break;
      case PhabricatorAuditActionConstants::ADD_CCS:
        $ccs = $request->getArr('ccs');
        $comments[] = id(new PhabricatorAuditComment())
          ->setAction(PhabricatorAuditActionConstants::ADD_CCS)
          ->setMetadata(
            array(
              PhabricatorAuditComment::METADATA_ADDED_CCS => $ccs,
            ));
        break;
      case PhabricatorAuditActionConstants::COMMENT:
        // We'll deal with this below.
        break;
      default:
        $comments[] = id(new PhabricatorAuditComment())
          ->setAction($action);
        break;
    }

    $content = $request->getStr('content');
    if (strlen($content)) {
      $comments[] = id(new PhabricatorAuditComment())
        ->setAction(PhabricatorAuditActionConstants::COMMENT)
        ->setContent($content);
    }

    id(new PhabricatorAuditCommentEditor($commit))
      ->setActor($user)
      ->setAttachInlineComments(true)
      ->addComments($comments);

    $handles = $this->loadViewerHandles($phids);
    $uri = $handles[$commit_phid]->getURI();

    $draft = id(new PhabricatorDraft())->loadOneWhere(
      'authorPHID = %s AND draftKey = %s',
      $user->getPHID(),
      'diffusion-audit-'.$commit->getID());
    if ($draft) {
      $draft->delete();
    }

    return id(new AphrontRedirectResponse())->setURI($uri);
  }

}
