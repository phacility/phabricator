<?php

final class PholioMockThumbGridView extends AphrontView {

  private $mock;

  public function setMock(PholioMock $mock) {
    $this->mock = $mock;
    return $this;
  }

  public function render() {
    $mock = $this->mock;

    $all_images = $mock->getAllImages();
    $all_images = mpull($all_images, null, 'getPHID');

    $history = mpull($all_images, 'getReplacesImagePHID', 'getPHID');

    $replaced = array();
    foreach ($history as $phid => $replaces_phid) {
      if ($replaces_phid) {
        $replaced[$replaces_phid] = true;
      }
    }

    // Figure out the columns. Start with all the active images.
    $images = mpull($mock->getImages(), null, 'getPHID');

    // Now, find deleted images: obsolete images which were not replaced.
    foreach ($mock->getAllImages() as $image) {
      if (!$image->getIsObsolete()) {
        // Image is current.
        continue;
      }

      if (isset($replaced[$image->getPHID()])) {
        // Image was replaced.
        continue;
      }

      // This is an obsolete image which was not replaced, so it must be
      // a deleted image.
      $images[$image->getPHID()] = $image;
    }

    $cols = array();
    $depth = 0;
    foreach ($images as $image) {
      $phid = $image->getPHID();

      $col = array();

      // If this is a deleted image, null out the final column.
      if ($image->getIsObsolete()) {
        $col[] = null;
      }

      $col[] = $phid;
      while ($phid && isset($history[$phid])) {
        $col[] = $history[$phid];
        $phid = $history[$phid];
      }

      $cols[] = $col;
      $depth = max($depth, count($col));
    }

    $grid = array();
    for ($ii = 0; $ii < $depth; $ii++) {
      $row = array();
      foreach ($cols as $col) {
        if (empty($col[$ii])) {
          $row[] = phutil_tag('td', array(), null);
        } else {
          $thumb = $this->renderThumbnail($all_images[$col[$ii]]);
          $row[] = phutil_tag('td', array(), $thumb);
        }
      }
      $grid[] = phutil_tag('tr', array(), $row);
    }

    $grid = phutil_tag(
      'table',
      array(
        'id' => 'pholio-mock-thumb-grid',
        'class' => 'pholio-mock-thumb-grid',
      ),
      $grid);

    return phutil_tag(
      'div',
      array(
        'class' => 'pholio-mock-thumb-grid-container',
      ),
      $grid);
  }


  private function renderThumbnail(PholioImage $image) {
    $thumbfile = $image->getFile();

    $dimensions = PhabricatorImageTransformer::getPreviewDimensions(
      $thumbfile,
      100);

    $tag = phutil_tag(
      'img',
      array(
        'width' => $dimensions['sdx'],
        'height' => $dimensions['sdy'],
        'src' => $thumbfile->getPreview100URI(),
        'class' => 'pholio-mock-thumb-grid-image',
        'style' => 'top: '.floor((100 - $dimensions['sdy'] ) / 2).'px',
    ));

    $classes = array('pholio-mock-thumb-grid-item');
    if ($image->getIsObsolete()) {
      $classes[] = 'pholio-mock-thumb-grid-item-obsolete';
    }

    return javelin_tag(
      'a',
      array(
        'sigil' => 'mock-thumbnail',
        'class' => implode(' ', $classes),
        'href' => '#',
        'meta' => array(
          'imageID' => $image->getID(),
        ),
      ),
      $tag);
  }

}
