<?php

abstract class PhabricatorConfigController extends PhabricatorController {

  public function shouldRequireAdmin() {
    return true;
  }

  public function buildHeaderView($text, $action = null) {
    $viewer = $this->getViewer();

    $file = PhabricatorFile::loadBuiltin($viewer, 'projects/v3/manage.png');
    $image = $file->getBestURI($file);
    $header = id(new PHUIHeaderView())
      ->setHeader($text)
      ->setProfileHeader(true)
      ->setImage($image);

    if ($action) {
      $header->addActionLink($action);
    }

    return $header;
  }

  public function buildConfigBoxView($title, $content, $action = null) {
    $header = id(new PHUIHeaderView())
      ->setHeader($title);

    if ($action) {
      $header->addActionItem($action);
    }

    $view = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->appendChild($content)
      ->setBackground(PHUIObjectBoxView::WHITE_CONFIG);

    return $view;
  }

}
