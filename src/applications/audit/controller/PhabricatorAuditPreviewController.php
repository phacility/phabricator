<?php

final class PhabricatorAuditPreviewController
  extends PhabricatorAuditController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $commit = id(new PhabricatorRepositoryCommit())->load($this->id);
    if (!$commit) {
      return new Aphront404Response();
    }

    $action = $request->getStr('action');

    $phids = array(
      $user->getPHID(),
      $commit->getPHID(),
    );

    $comments = array();

    if ($action != PhabricatorAuditActionConstants::COMMENT) {
      $action_comment = id(new PhabricatorAuditComment())
        ->setActorPHID($user->getPHID())
        ->setTargetPHID($commit->getPHID())
        ->setAction($action);

      $auditors = $request->getStrList('auditors');
      if ($action == PhabricatorAuditActionConstants::ADD_AUDITORS &&
        $auditors) {

        $action_comment->setMetadata(array(
          PhabricatorAuditComment::METADATA_ADDED_AUDITORS => $auditors));
        $phids = array_merge($phids, $auditors);
      }

      $ccs = $request->getStrList('ccs');
      if ($action == PhabricatorAuditActionConstants::ADD_CCS && $ccs) {
        $action_comment->setMetadata(array(
          PhabricatorAuditComment::METADATA_ADDED_CCS => $ccs));
        $phids = array_merge($phids, $ccs);
      }

      $comments[] = $action_comment;
    }

    $content = $request->getStr('content');
    if (strlen($content)) {
      $comments[] = id(new PhabricatorAuditComment())
        ->setActorPHID($user->getPHID())
        ->setTargetPHID($commit->getPHID())
        ->setAction(PhabricatorAuditActionConstants::COMMENT)
        ->setContent($content);
    }

    $engine = new PhabricatorMarkupEngine();
    $engine->setViewer($user);
    foreach ($comments as $comment) {
      $engine->addObject(
        $comment,
        PhabricatorAuditComment::MARKUP_FIELD_BODY);
    }
    $engine->process();

    $views = array();
    foreach ($comments as $comment) {
      $view = id(new DiffusionCommentView())
        ->setMarkupEngine($engine)
        ->setUser($user)
        ->setComment($comment)
        ->setIsPreview(true);

      $phids = array_merge($phids, $view->getRequiredHandlePHIDs());
      $views[] = $view;
    }

    $handles = $this->loadViewerHandles($phids);

    foreach ($views as $view) {
      $view->setHandles($handles);
    }

    id(new PhabricatorDraft())
      ->setAuthorPHID($user->getPHID())
      ->setDraftKey('diffusion-audit-'.$this->id)
      ->setDraft($content)
      ->replaceOrDelete();

    return id(new AphrontAjaxResponse())->setContent(hsprintf('%s', $views));
  }

}
