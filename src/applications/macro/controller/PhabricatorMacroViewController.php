<?php

final class PhabricatorMacroViewController
  extends PhabricatorMacroController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $macro = id(new PhabricatorMacroQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->needFiles(true)
      ->executeOne();
    if (!$macro) {
      return new Aphront404Response();
    }

    $file = $macro->getFile();

    $title_short = pht('Macro "%s"', $macro->getName());
    $title_long  = pht('Image Macro "%s"', $macro->getName());

    $actions = $this->buildActionView($macro);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(
      $title_short,
      $this->getApplicationURI('/view/'.$macro->getID().'/'));

    $properties = $this->buildPropertyView($macro, $actions);
    if ($file) {
      $file_view = new PHUIPropertyListView();
      $file_view->addImageContent(
        phutil_tag(
          'img',
          array(
            'src'     => $file->getViewURI(),
            'class'   => 'phabricator-image-macro-hero',
          )));
    }

    $timeline = $this->buildTransactionTimeline(
      $macro,
      new PhabricatorMacroTransactionQuery());

    $header = id(new PHUIHeaderView())
      ->setUser($viewer)
      ->setPolicyObject($macro)
      ->setHeader($title_long);

    if (!$macro->getIsDisabled()) {
      $header->setStatus('fa-check', 'bluegrey', pht('Active'));
    } else {
      $header->setStatus('fa-ban', 'red', pht('Archived'));
    }

    $is_serious = PhabricatorEnv::getEnvConfig('phabricator.serious-business');

    $comment_header = $is_serious
      ? pht('Add Comment')
      : pht('Grovel in Awe');

    $draft = PhabricatorDraft::newFromUserAndKey($viewer, $macro->getPHID());

    $add_comment_form = id(new PhabricatorApplicationTransactionCommentView())
      ->setUser($viewer)
      ->setObjectPHID($macro->getPHID())
      ->setDraft($draft)
      ->setHeaderText($comment_header)
      ->setAction($this->getApplicationURI('/comment/'.$macro->getID().'/'))
      ->setSubmitButtonName(pht('Add Comment'));

    $object_box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($properties);

    if ($file_view) {
      $object_box->addPropertyList($file_view);
    }

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $object_box,
        $timeline,
        $add_comment_form,
      ),
      array(
        'title' => $title_short,
        'pageObjects' => array($macro->getPHID()),
      ));
  }

  private function buildActionView(PhabricatorFileImageMacro $macro) {
    $can_manage = $this->hasApplicationCapability(
      PhabricatorMacroManageCapability::CAPABILITY);

    $request = $this->getRequest();
    $view = id(new PhabricatorActionListView())
      ->setUser($request->getUser())
      ->setObject($macro)
      ->setObjectURI($request->getRequestURI())
      ->addAction(
        id(new PhabricatorActionView())
        ->setName(pht('Edit Macro'))
        ->setHref($this->getApplicationURI('/edit/'.$macro->getID().'/'))
        ->setDisabled(!$can_manage)
        ->setWorkflow(!$can_manage)
        ->setIcon('fa-pencil'));

    $view->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit Audio'))
        ->setHref($this->getApplicationURI('/audio/'.$macro->getID().'/'))
        ->setDisabled(!$can_manage)
        ->setWorkflow(!$can_manage)
        ->setIcon('fa-music'));

    if ($macro->getIsDisabled()) {
      $view->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Activate Macro'))
          ->setHref($this->getApplicationURI('/disable/'.$macro->getID().'/'))
          ->setWorkflow(true)
          ->setDisabled(!$can_manage)
          ->setIcon('fa-check'));
    } else {
      $view->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Archive Macro'))
          ->setHref($this->getApplicationURI('/disable/'.$macro->getID().'/'))
          ->setWorkflow(true)
          ->setDisabled(!$can_manage)
          ->setIcon('fa-ban'));
    }

    return $view;
  }

  private function buildPropertyView(
    PhabricatorFileImageMacro $macro,
    PhabricatorActionListView $actions) {
    $viewer = $this->getViewer();

    $view = id(new PHUIPropertyListView())
      ->setUser($this->getRequest()->getUser())
      ->setObject($macro)
      ->setActionList($actions);

    switch ($macro->getAudioBehavior()) {
      case PhabricatorFileImageMacro::AUDIO_BEHAVIOR_ONCE:
        $view->addProperty(pht('Audio Behavior'), pht('Play Once'));
        break;
      case PhabricatorFileImageMacro::AUDIO_BEHAVIOR_LOOP:
        $view->addProperty(pht('Audio Behavior'), pht('Loop'));
        break;
    }

    $audio_phid = $macro->getAudioPHID();
    if ($audio_phid) {
      $view->addProperty(
        pht('Audio'),
        $viewer->renderHandle($audio_phid));
    }

    $view->invokeWillRenderEvent();

    return $view;
  }

}
