<?php

/**
 * @group pholio
 */
final class PholioUploadedImageView extends AphrontView {

  private $image;

  public function setImage(PholioImage $image) {
    $this->image = $image;
    return $this;
  }

  public function render() {
    require_celerity_resource('pholio-edit-css');

    $image = $this->image;
    $file = $image->getFile();
    $phid = $file->getPHID();

    $thumb = phutil_tag(
      'img',
      array(
        'src'     => $file->getThumb280x210URI(),
        'width'   => 280,
        'height'  => 210,
      ));

    $remove = $this->renderRemoveElement();

    $title = id(new AphrontFormTextControl())
      ->setName('title_'.$phid)
      ->setValue($image->getName())
      ->setLabel(pht('Title'));

    $description = id(new AphrontFormTextAreaControl())
      ->setName('description_'.$phid)
      ->setValue($image->getDescription())
      ->setLabel(pht('Description'));

    $content = hsprintf(
      '<div class="thumb-box">
        <div class="title">
          <div class="text">%s</div>
          <div class="remove">%s</div>
        </div>
        <div class="thumb">%s</div>
      </div>
      <div class="image-data">
        <div class="title">%s</div>
        <div class="description">%s</div>
      </div>',
      $file->getName(),
      $remove,
      $thumb,
      $title,
      $description);

    $input = phutil_tag(
      'input',
      array(
        'type' => 'hidden',
        'name' => 'file_phids[]',
        'value' => $phid,
      ));

    return javelin_tag(
      'div',
      array(
        'class' => 'pholio-uploaded-image',
        'sigil' => 'pholio-drop-image',
      ),
      array(
        $content,
        $input,
      ));
  }

  private function renderRemoveElement() {
    return javelin_tag(
      'a',
      array(
        'class' => 'button grey',
        'sigil' => 'pholio-drop-remove',
      ),
      'X');
  }

}
