<?php

final class PhabricatorFileDocumentRenderingEngine
  extends PhabricatorDocumentRenderingEngine {

  protected function newRefViewURI(
    PhabricatorDocumentRef $ref,
    PhabricatorDocumentEngine $engine) {

    $file = $ref->getFile();
    $engine_key = $engine->getDocumentEngineKey();

    return urisprintf(
      '/file/view/%d/%s/',
      $file->getID(),
      $engine_key);
  }

  protected function newRefRenderURI(
    PhabricatorDocumentRef $ref,
    PhabricatorDocumentEngine $engine) {
    $file = $ref->getFile();
    if (!$file) {
      throw new PhutilMethodNotImplementedException();
    }

    $engine_key = $engine->getDocumentEngineKey();
    $file_phid = $file->getPHID();

    return urisprintf(
      '/file/document/%s/%s/',
      $engine_key,
      $file_phid);
  }

  protected function addApplicationCrumbs(
    PHUICrumbsView $crumbs,
    PhabricatorDocumentRef $ref = null) {

    if ($ref) {
      $file = $ref->getFile();
      if ($file) {
        $crumbs->addTextCrumb($file->getMonogram(), $file->getInfoURI());
      }
    }

  }

}
