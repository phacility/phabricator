<?php

final class DifferentialCommentSaveController extends DifferentialController {

  public function processRequest() {
    $request = $this->getRequest();
    if (!$request->isFormPost()) {
      return new Aphront400Response();
    }

    $revision_id = $request->getInt('revision_id');
    $revision = id(new DifferentialRevision())->load($revision_id);
    if (!$revision) {
      return new Aphront400Response();
    }

    $comment    = $request->getStr('comment');
    $action     = $request->getStr('action');
    $reviewers  = $request->getArr('reviewers');
    $ccs        = $request->getArr('ccs');
    
    // Allow projects to be added as a reviewer by adding each current member
    // of the porject.
    foreach ($reviewers as $key => $phid) {
    	if (phid_get_type($phid) == "PROJ") {
    		unset($reviewers[$key]);
    		$project = new PhabricatorProject($phid);
		    $new_phids = PhabricatorEdgeQuery::loadDestinationPHIDs(
		    	$phid,
		    	PhabricatorEdgeConfig::TYPE_PROJ_MEMBER);
    		
    		foreach ($new_phids as $new_phid) {
    			array_push($reviewers, $new_phid);
    		}
    	}
    }

    $editor = new DifferentialCommentEditor(
      $revision,
      $action);

    $content_source = PhabricatorContentSource::newForSource(
      PhabricatorContentSource::SOURCE_WEB,
      array(
        'ip' => $request->getRemoteAddr(),
      ));

    try {
      $editor
        ->setActor($request->getUser())
        ->setMessage($comment)
        ->setContentSource($content_source)
        ->setAttachInlineComments(true)
        ->setAddedReviewers($reviewers)
        ->setAddedCCs($ccs)
        ->save();
    } catch (DifferentialActionHasNoEffectException $no_effect) {
      $has_inlines = id(new DifferentialInlineCommentQuery())
        ->withDraftComments($request->getUser()->getPHID(), $revision->getID())
        ->execute();

      $dialog = new AphrontDialogView();
      $dialog->setUser($request->getUser());
      $dialog->addCancelButton('/D'.$revision_id);

      $dialog->addHiddenInput('revision_id',  $revision_id);
      $dialog->addHiddenInput('action',       'none');
      $dialog->addHiddenInput('reviewers',    $reviewers);
      $dialog->addHiddenInput('ccs',          $ccs);
      $dialog->addHiddenInput('comment',      $comment);

      $dialog->setTitle(pht('Action Has No Effect'));
      $dialog->appendChild(
        phutil_tag('p', array(), $no_effect->getMessage()));

      if (strlen($comment) || $has_inlines) {
        $dialog->addSubmitButton(pht('Post as Comment'));
        $dialog->appendChild(phutil_tag('br'));
        $dialog->appendChild(phutil_tag('p', array(), pht(
          'Do you want to post your feedback anyway, as a normal comment?')));
      }

      return id(new AphrontDialogResponse())->setDialog($dialog);
    }

    // TODO: Diff change detection?

    $draft = id(new PhabricatorDraft())->loadOneWhere(
      'authorPHID = %s AND draftKey = %s',
      $request->getUser()->getPHID(),
      'differential-comment-'.$revision->getID());
    if ($draft) {
      $draft->delete();
    }

    return id(new AphrontRedirectResponse())
      ->setURI('/D'.$revision->getID());
  }

}
