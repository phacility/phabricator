<?php

/**
 * @group pholio
 */
final class PholioUploadedImageView extends AphrontAbstractAttachedFileView {

  private $images = array();

  public function setImages(array $images) {
    assert_instances_of($images, 'PholioImage');
    $this->images = $images;
    return $this;
  }
  public function getImages() {
    return $this->images;
  }
  public function getImage($phid) {
    $images = $this->getImages();
    return idx($images, $phid, new PholioImage());
  }

  public function render() {
    require_celerity_resource('pholio-edit-css');

    $file = $this->getFile();
    $phid = $file->getPHID();
    $image = $this->getImage($phid);

    $thumb = phutil_tag(
      'img',
      array(
        'src'     => $file->getThumb280x210URI(),
        'width'   => 280,
        'height'  => 210,
      ));

    $file_link = $this->getName();
    if (!$image->getName()) {
      $image->setName($this->getFile()->getName());
    }
    $remove = $this->getRemoveElement();

    $title = id(new AphrontFormTextControl())
      ->setName('title_'.$phid)
      ->setValue($image->getName())
      ->setLabel(pht('Title'));

    $description = id(new AphrontFormTextAreaControl())
      ->setName('description_'.$phid)
      ->setValue($image->getDescription())
      ->setLabel(pht('Description'));

    return hsprintf(
      '<div class="pholio-uploaded-image">
        <div class="thumb-box">
          <div class="title">
            <div class="text">%s</div>
            <div class="remove">%s</div>
          </div>
          <div class="thumb">%s</div>
        </div>
        <div class="image-data">
          <div class="title">%s</title>
          <div class="description">%s</description>
        </div>',
      $file_link,
      $remove,
      $thumb,
      $title,
      $description);

  }

}
