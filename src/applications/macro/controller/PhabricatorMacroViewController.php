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

    $title_short = pht('Macro "%s"', $macro->getName());
    $title_long  = pht('Image Macro "%s"', $macro->getName());

    $actions = $this->buildActionView($macro);
    $subheader = $this->buildSubheaderView($macro);
    $properties = $this->buildPropertyView($macro);
    $file = $this->buildFileView($macro);
    $details = $this->buildPropertySectionView($macro);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb($macro->getName());
    $crumbs->setBorder(true);

    $timeline = $this->buildTransactionTimeline(
      $macro,
      new PhabricatorMacroTransactionQuery());

    $header = id(new PHUIHeaderView())
      ->setUser($viewer)
      ->setPolicyObject($macro)
      ->setHeader($macro->getName())
      ->setHeaderIcon('fa-file-image-o');

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

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setSubheader($subheader)
      ->setMainColumn(array(
        $timeline,
        $add_comment_form,
      ))
      ->addPropertySection(pht('MACRO'), $file)
      ->addPropertySection(pht('DETAILS'), $details)
      ->setPropertyList($properties)
      ->setActionList($actions);

    return $this->newPage()
      ->setTitle($title_short)
      ->setCrumbs($crumbs)
      ->setPageObjectPHIDs(array($macro->getPHID()))
      ->appendChild(
        array(
          $view,
      ));
  }

  private function buildActionView(
    PhabricatorFileImageMacro $macro) {
    $can_manage = $this->hasApplicationCapability(
      PhabricatorMacroManageCapability::CAPABILITY);

    $request = $this->getRequest();
    $view = id(new PhabricatorActionListView())
      ->setUser($request->getUser())
      ->setObject($macro)
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

  private function buildSubheaderView(
    PhabricatorFileImageMacro $macro) {
    $viewer = $this->getViewer();

    $author_phid = $macro->getAuthorPHID();

    $author = $viewer->renderHandle($author_phid)->render();
    $date = phabricator_datetime($macro->getDateCreated(), $viewer);
    $author = phutil_tag('strong', array(), $author);

    $handles = $viewer->loadHandles(array($author_phid));
    $image_uri = $handles[$author_phid]->getImageURI();
    $image_href = $handles[$author_phid]->getURI();

    $content = pht('Masterfully imagined by %s on %s.', $author, $date);

    return id(new PHUIHeadThingView())
      ->setImage($image_uri)
      ->setImageHref($image_href)
      ->setContent($content);
  }

  private function buildPropertySectionView(
    PhabricatorFileImageMacro $macro) {
    $viewer = $this->getViewer();

    $view = id(new PHUIPropertyListView())
      ->setUser($viewer);

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

    if ($view->hasAnyProperties()) {
      return $view;
    }

    return null;
  }

  private function buildFileView(
    PhabricatorFileImageMacro $macro) {
    $viewer = $this->getViewer();

    $view = id(new PHUIPropertyListView())
      ->setUser($viewer);

    $file = $macro->getFile();
    if ($file) {
      $view->addImageContent(
        phutil_tag(
          'img',
          array(
            'src'     => $file->getViewURI(),
            'class'   => 'phabricator-image-macro-hero',
          )));
      return $view;
    }
    return null;
  }

  private function buildPropertyView(
    PhabricatorFileImageMacro $macro) {
    $viewer = $this->getViewer();

    $view = id(new PHUIPropertyListView())
      ->setUser($this->getRequest()->getUser())
      ->setObject($macro);

    $view->invokeWillRenderEvent();

    return $view;
  }

}
