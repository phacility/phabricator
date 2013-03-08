<?php

final class ConpherenceFileWidgetView extends AphrontView {

  private $conpherence;
  private $updateURI;

  public function setUpdateURI($update_uri) {
    $this->updateURI = $update_uri;
    return $this;
  }
  public function getUpdateURI() {
    return $this->updateURI;
  }

  public function setConpherence(ConpherenceThread $conpherence) {
    $this->conpherence = $conpherence;
    return $this;
  }
  public function getConpherence() {
    return $this->conpherence;
  }

  public function render() {
    $conpherence = $this->getConpherence();
    $widget_data = $conpherence->getWidgetData();
    $files = $widget_data['files'];

    $table_data = array();

    foreach ($files as $file) {
      $file_view = id(new PhabricatorFileLinkView())
        ->setFilePHID($file->getPHID())
        ->setFileName($file->getName())
        ->setFileViewable(true)
        ->setFileViewURI($file->getBestURI());
      $meta = $file_view->getMetadata();

      $table_data[] = array(
        javelin_tag(
          'a',
          array(
            'sigil' => 'lightboxable',
            'meta' => $meta
          ),
          phutil_tag(
            'img',
            array(
              'src' => $file->getThumb60x45URI()
            ),
            '')),
        $file_view->render()
      );
    }
    $header = id(new PhabricatorHeaderView())
      ->setHeader(pht('Attached Files'));
    $table = id(new AphrontTableView($table_data))
        ->setNoDataString(pht('No files attached to conpherence.'))
        ->setHeaders(array('', pht('Name')))
        ->setColumnClasses(array('', 'wide wrap'));
    return $this->renderSingleView(array($header, $table));

  }

}
