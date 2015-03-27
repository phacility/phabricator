<?php

abstract class PhabricatorInlineCommentPreviewController
  extends PhabricatorController {

  abstract protected function loadInlineComments();

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $inlines = $this->loadInlineComments();
    assert_instances_of($inlines, 'PhabricatorInlineCommentInterface');

    $engine = new PhabricatorMarkupEngine();
    $engine->setViewer($viewer);
    foreach ($inlines as $inline) {
      $engine->addObject(
        $inline,
        PhabricatorInlineCommentInterface::MARKUP_FIELD_BODY);
    }
    $engine->process();

    $phids = array($viewer->getPHID());
    $handles = $this->loadViewerHandles($phids);

    $views = array();
    foreach ($inlines as $inline) {
      // TODO: This is incorrect, but figuring it out is somewhat involved.
      $object_owner_phid = null;

      $view = id(new PHUIDiffInlineCommentDetailView())
        ->setInlineComment($inline)
        ->setMarkupEngine($engine)
        ->setHandles($handles)
        ->setEditable(false)
        ->setPreview(true)
        ->setCanMarkDone(false)
        ->setObjectOwnerPHID($object_owner_phid);
      $views[] = $view->render();
    }
    $views = phutil_implode_html("\n", $views);

    return id(new AphrontAjaxResponse())
      ->setContent($views);
  }
}
