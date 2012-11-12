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

    $action = $request->getStr('action');

    $comment = id(new PhabricatorAuditComment())
      ->setAction($action)
      ->setContent($request->getStr('content'));

    // make sure we only add auditors or ccs if the action matches
    switch ($action) {
      case 'add_auditors':
        $auditors = $request->getArr('auditors');
        $ccs = array();
        break;
      case 'add_ccs':
        $auditors = array();
        $ccs = $request->getArr('ccs');
        break;
      default:
        $auditors = array();
        $ccs = array();
        break;
    }

    id(new PhabricatorAuditCommentEditor($commit))
      ->setActor($user)
      ->setAttachInlineComments(true)
      ->addAuditors($auditors)
      ->addCCs($ccs)
      ->addComment($comment);

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
