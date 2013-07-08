<?php

final class PhabricatorMacroViewController
  extends PhabricatorMacroController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $macro = id(new PhabricatorMacroQuery())
      ->setViewer($user)
      ->withIDs(array($this->id))
      ->executeOne();
    if (!$macro) {
      return new Aphront404Response();
    }

    $file = $macro->getFile();

    $title_short = pht('Macro "%s"', $macro->getName());
    $title_long  = pht('Image Macro "%s"', $macro->getName());

    $actions = $this->buildActionView($macro);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->setActionList($actions);
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setHref($this->getApplicationURI('/view/'.$macro->getID().'/'))
        ->setName($title_short));

    $properties = $this->buildPropertyView($macro, $file);

    $xactions = id(new PhabricatorMacroTransactionQuery())
      ->setViewer($request->getUser())
      ->withObjectPHIDs(array($macro->getPHID()))
      ->execute();

    $engine = id(new PhabricatorMarkupEngine())
      ->setViewer($user);
    foreach ($xactions as $xaction) {
      if ($xaction->getComment()) {
        $engine->addObject(
          $xaction->getComment(),
          PhabricatorApplicationTransactionComment::MARKUP_FIELD_COMMENT);
      }
    }
    $engine->process();

    $timeline = id(new PhabricatorApplicationTransactionView())
      ->setUser($user)
      ->setTransactions($xactions)
      ->setMarkupEngine($engine);

    $header = id(new PhabricatorHeaderView())
      ->setHeader($title_long);

    if ($macro->getIsDisabled()) {
      $header->addTag(
        id(new PhabricatorTagView())
          ->setType(PhabricatorTagView::TYPE_STATE)
          ->setName(pht('Macro Disabled'))
          ->setBackgroundColor(PhabricatorTagView::COLOR_BLACK));
    }

    $is_serious = PhabricatorEnv::getEnvConfig('phabricator.serious-business');

    $add_comment_header = id(new PhabricatorHeaderView())
      ->setHeader(
        $is_serious
          ? pht('Add Comment')
          : pht('Grovel in Awe'));

    $submit_button_name = $is_serious
      ? pht('Add Comment')
      : pht('Lavish Praise');

    $draft = PhabricatorDraft::newFromUserAndKey($user, $macro->getPHID());

    $add_comment_form = id(new PhabricatorApplicationTransactionCommentView())
      ->setUser($user)
      ->setDraft($draft)
      ->setAction($this->getApplicationURI('/comment/'.$macro->getID().'/'))
      ->setSubmitButtonName($submit_button_name);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $header,
        $actions,
        $properties,
        $timeline,
        $add_comment_header,
        $add_comment_form,
      ),
      array(
        'title' => $title_short,
        'device' => true,
      ));
  }

  private function buildActionView(PhabricatorFileImageMacro $macro) {
    $view = new PhabricatorActionListView();
    $view->setUser($this->getRequest()->getUser());
    $view->setObject($macro);
    $view->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit Macro'))
        ->setHref($this->getApplicationURI('/edit/'.$macro->getID().'/'))
        ->setIcon('edit'));

    if ($macro->getIsDisabled()) {
      $view->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Restore Macro'))
          ->setHref($this->getApplicationURI('/disable/'.$macro->getID().'/'))
          ->setWorkflow(true)
          ->setIcon('undo'));
    } else {
      $view->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Disable Macro'))
          ->setHref($this->getApplicationURI('/disable/'.$macro->getID().'/'))
          ->setWorkflow(true)
          ->setIcon('delete'));
    }

    return $view;
  }

  private function buildPropertyView(
    PhabricatorFileImageMacro $macro,
    PhabricatorFile $file = null) {

    $view = id(new PhabricatorPropertyListView())
      ->setUser($this->getRequest()->getUser())
      ->setObject($macro);

    $view->invokeWillRenderEvent();

    if ($file) {
      $view->addImageContent(
        phutil_tag(
          'img',
          array(
            'src'     => $file->getViewURI(),
            'class'   => 'phabricator-image-macro-hero',
          )));
    }

    return $view;
  }

}
