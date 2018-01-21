<?php

final class ManiphestTaskBulkEngine
  extends PhabricatorBulkEngine {

  private $workboard;

  public function setWorkboard(PhabricatorProject $workboard) {
    $this->workboard = $workboard;
    return $this;
  }

  public function getWorkboard() {
    return $this->workboard;
  }

  public function newSearchEngine() {
    return new ManiphestTaskSearchEngine();
  }

  public function newEditEngine() {
    return new ManiphestEditEngine();
  }

  public function getDoneURI() {
    $board_uri = $this->getBoardURI();
    if ($board_uri) {
      return $board_uri;
    }

    return parent::getDoneURI();
  }

  public function getCancelURI() {
    $board_uri = $this->getBoardURI();
    if ($board_uri) {
      return $board_uri;
    }

    return parent::getCancelURI();
  }

  private function getBoardURI() {
    $workboard = $this->getWorkboard();

    if ($workboard) {
      $project_id = $workboard->getID();
      return "/project/board/{$project_id}/";
    }

    return null;
  }

}
