<?php

abstract class PhabricatorInlineCommentPreviewController
  extends PhabricatorController {

  abstract protected function loadInlineComments();

  public function processRequest() {
    $request = $this->getRequest();
    $user    = $request->getUser();

    $inlines = $this->loadInlineComments();
    assert_instances_of($inlines, 'PhabricatorInlineCommentInterface');

    $engine = new PhabricatorMarkupEngine();
    $engine->setViewer($user);
    foreach ($inlines as $inline) {
      $engine->addObject(
        $inline,
        PhabricatorInlineCommentInterface::MARKUP_FIELD_BODY);
    }
    $engine->process();

    $phids = array($user->getPHID());
    $handles = $this->loadViewerHandles($phids);

    $views = array();
    foreach ($inlines as $inline) {
      $view = new DifferentialInlineCommentView();
      $view->setInlineComment($inline);
      $view->setMarkupEngine($engine);
      $view->setHandles($handles);
      $view->setEditable(false);
      $view->setPreview(true);
      $views[] = $view->render();
    }
    $views = phutil_implode_html("\n", $views);

    return id(new AphrontAjaxResponse())
      ->setContent($views);
  }
}
