<?php

final class PhabricatorFileDocumentController
  extends PhabricatorFileController {

  private $file;
  private $engine;
  private $ref;

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $file_phid = $request->getURIData('phid');

    $file = id(new PhabricatorFileQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($file_phid))
      ->executeOne();
    if (!$file) {
      return $this->newErrorResponse(
        pht(
          'This file ("%s") does not exist or could not be loaded.',
          $file_phid));
    }
    $this->file = $file;

    $ref = id(new PhabricatorDocumentRef())
      ->setFile($file);
    $this->ref = $ref;

    $engines = PhabricatorDocumentEngine::getEnginesForRef($viewer, $ref);
    $engine_key = $request->getURIData('engineKey');
    if (!isset($engines[$engine_key])) {
      return $this->newErrorResponse(
        pht(
          'The engine ("%s") is unknown, or unable to render this document.',
          $engine_key));
    }
    $engine = $engines[$engine_key];
    $this->engine = $engine;

    try {
      $content = $engine->newDocument($ref);
    } catch (Exception $ex) {
      return $this->newErrorResponse($ex->getMessage());
    }

    return $this->newContentResponse($content);
  }

  private function newErrorResponse($message) {
    $container = phutil_tag(
      'div',
      array(
        'class' => 'document-engine-error',
      ),
      array(
        id(new PHUIIconView())
          ->setIcon('fa-exclamation-triangle red'),
        ' ',
        $message,
      ));

    return $this->newContentResponse($container);
  }


  private function newContentResponse($content) {
    $viewer = $this->getViewer();
    $request = $this->getRequest();

    $file = $this->file;
    $engine = $this->engine;
    $ref = $this->ref;

    if ($request->isAjax()) {
      return id(new AphrontAjaxResponse())
        ->setContent(
          array(
            'markup' => hsprintf('%s', $content),
          ));
    }

    $crumbs = $this->buildApplicationCrumbs();
    if ($file) {
      $crumbs->addTextCrumb($file->getMonogram(), $file->getInfoURI());
    }

    $label = $engine->getViewAsLabel($ref);
    if ($label) {
      $crumbs->addTextCrumb($label);
    }

    $crumbs->setBorder(true);

    $content_frame = id(new PHUIObjectBoxView())
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->appendChild($content);

    $page_frame = id(new PHUITwoColumnView())
      ->setFooter($content_frame);

    return $this->newPage()
      ->setCrumbs($crumbs)
      ->setTitle(
        array(
          $ref->getName(),
          pht('Standalone'),
        ))
      ->appendChild($page_frame);
  }

}
