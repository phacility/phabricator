<?php

final class DiffusionDocumentRenderingEngine
  extends PhabricatorDocumentRenderingEngine {

  private $diffusionRequest;

  public function setDiffusionRequest(DiffusionRequest $drequest) {
    $this->diffusionRequest = $drequest;
    return $this;
  }

  public function getDiffusionRequest() {
    return $this->diffusionRequest;
  }

  protected function newRefViewURI(
    PhabricatorDocumentRef $ref,
    PhabricatorDocumentEngine $engine) {

    $file = $ref->getFile();
    $engine_key = $engine->getDocumentEngineKey();
    $drequest = $this->getDiffusionRequest();

    return (string)$drequest->generateURI(
      array(
        'action' => 'browse',
        'stable' => true,
        'params' => array(
          'as' => $engine_key,
        ),
      ));
  }

  protected function newRefRenderURI(
    PhabricatorDocumentRef $ref,
    PhabricatorDocumentEngine $engine) {

    $engine_key = $engine->getDocumentEngineKey();

    $file = $ref->getFile();
    $file_phid = $file->getPHID();

    $drequest = $this->getDiffusionRequest();

    return (string)$drequest->generateURI(
      array(
        'action' => 'document',
        'stable' => true,
        'params' => array(
          'as' => $engine_key,
          'filePHID' => $file_phid,
        ),
      ));
  }

  protected function getSelectedDocumentEngineKey() {
    return $this->getRequest()->getStr('as');
  }

  protected function getSelectedLineRange() {
    $range = $this->getDiffusionRequest()->getLine();
    return AphrontRequest::parseURILineRange($range, 1000);
  }

  protected function addApplicationCrumbs(
    PHUICrumbsView $crumbs,
    PhabricatorDocumentRef $ref = null) {
    return;
  }

}
