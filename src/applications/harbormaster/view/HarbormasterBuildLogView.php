<?php

final class HarbormasterBuildLogView extends AphrontView {

  private $log;

  public function setBuildLog(HarbormasterBuildLog $log) {
    $this->log = $log;
    return $this;
  }

  public function getBuildLog() {
    return $this->log;
  }

  public function render() {
    $viewer = $this->getViewer();
    $log = $this->getBuildLog();
    $id = $log->getID();

    $header = id(new PHUIHeaderView())
      ->setViewer($viewer)
      ->setHeader(pht('Build Log %d', $id));

    $download_uri = "/harbormaster/log/download/{$id}/";

    $download_button = id(new PHUIButtonView())
      ->setTag('a')
      ->setHref($download_uri)
      ->setIcon('fa-download')
      ->setDisabled(!$log->getFilePHID())
      ->setWorkflow(true)
      ->setText(pht('Download Log'));

    $header->addActionLink($download_button);

    $box_view = id(new PHUIObjectBoxView())
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setHeader($header)
      ->appendChild('...');

    return $box_view;
  }

}
