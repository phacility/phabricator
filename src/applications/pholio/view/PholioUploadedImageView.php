<?php

final class PholioUploadedImageView extends AphrontView {

  private $image;
  private $replacesPHID;

  public function setReplacesPHID($replaces_phid) {
    $this->replacesPHID = $replaces_phid;
    return $this;
  }

  public function setImage(PholioImage $image) {
    $this->image = $image;
    return $this;
  }

  public function render() {
    require_celerity_resource('pholio-edit-css');

    $image = $this->image;
    $file = $image->getFile();
    $phid = $file->getPHID();
    $replaces_phid = $this->replacesPHID;

    $remove = $this->renderRemoveElement();

    $title = id(new AphrontFormTextControl())
      ->setName('title_'.$phid)
      ->setValue($image->getName())
      ->setSigil('image-title')
      ->setLabel(pht('Title'));

    $description = id(new PhabricatorRemarkupControl())
      ->setUser($this->getUser())
      ->setName('description_'.$phid)
      ->setValue($image->getDescription())
      ->setSigil('image-description')
      ->setLabel(pht('Description'));

    $xform = PhabricatorFileTransform::getTransformByKey(
      PhabricatorFileThumbnailTransform::TRANSFORM_PINBOARD);
    $thumbnail_uri = $file->getURIForTransform($xform);

    $thumb_img = javelin_tag(
      'img',
      array(
        'class' => 'pholio-thumb-img',
        'src' => $thumbnail_uri,
        'sigil' => 'pholio-uploaded-thumb',
      ));

    $thumb_frame = phutil_tag(
      'div',
      array(
        'class' => 'pholio-thumb-frame',
      ),
      $thumb_img);

    $handle = javelin_tag(
      'div',
      array(
        'class' => 'pholio-drag-handle',
        'sigil' => 'pholio-drag-handle',
      ));

    $content = hsprintf(
      '<div class="pholio-thumb-box">
        <div class="pholio-thumb-title">
          %s
          <div class="pholio-thumb-name">%s</div>
        </div>
        %s
      </div>
      <div class="pholio-image-details">
        %s
        %s
      </div>',
      $remove,
      $file->getName(),
      $thumb_frame,
      $title,
      $description);

    $input = phutil_tag(
      'input',
      array(
        'type' => 'hidden',
        'name' => 'file_phids[]',
        'value' => $phid,
      ));

    $replaces_input = phutil_tag(
      'input',
      array(
        'type' => 'hidden',
        'name' => 'replaces['.$replaces_phid.']',
        'value' => $phid,
      ));

    return javelin_tag(
      'div',
      array(
        'class' => 'pholio-uploaded-image',
        'sigil' => 'pholio-drop-image',
        'meta'  => array(
          'filePHID' => $file->getPHID(),
          'replacesPHID' => $replaces_phid,
        ),
      ),
      array(
        $handle,
        $content,
        $input,
        $replaces_input,
      ));
  }

  private function renderRemoveElement() {
    return javelin_tag(
      'a',
      array(
        'class' => 'button button-grey',
        'sigil' => 'pholio-drop-remove',
      ),
      'X');
  }

}
