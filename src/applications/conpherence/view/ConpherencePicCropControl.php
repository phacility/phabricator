<?php

final class ConpherencePicCropControl extends AphrontFormControl {

  protected function getCustomControlClass() {
    return 'aphront-form-crop';
  }

  protected function renderInput() {
    $width = ConpherenceImageData::CROP_WIDTH;
    $height = ConpherenceImageData::CROP_HEIGHT;

    $file = $this->getValue();

    if ($file === null) {
      return phutil_tag(
        'img',
        array(
          'src' => PhabricatorUser::getDefaultProfileImageURI(),
        ),
        '');
    }

    $c_id = celerity_generate_unique_node_id();
    $metadata = $file->getMetadata();
    $scale = PhabricatorImageTransformer::getScaleForCrop(
      $file,
      $width,
      $height);

    Javelin::initBehavior(
      'aphront-crop',
      array(
        'cropBoxID' => $c_id,
        'width' => $width,
        'height' => $height,
        'scale' => $scale,
        'imageH' => $metadata[PhabricatorFile::METADATA_IMAGE_HEIGHT],
        'imageW' => $metadata[PhabricatorFile::METADATA_IMAGE_WIDTH],
      ));

    return javelin_tag(
      'div',
      array(
        'id' => $c_id,
        'sigil' => 'crop-box',
        'mustcapture' => true,
        'class' => 'crop-box',
      ),
      array(
        javelin_tag(
          'img',
          array(
            'src' => $file->getBestURI(),
            'class' => 'crop-image',
            'sigil' => 'crop-image',
          ),
          ''),
        javelin_tag(
          'input',
          array(
            'type' => 'hidden',
            'name' => 'image_x',
            'sigil' => 'crop-x',
          ),
          ''),
        javelin_tag(
          'input',
          array(
            'type' => 'hidden',
            'name' => 'image_y',
            'sigil' => 'crop-y',
          ),
          ''),
      ));
  }

}
