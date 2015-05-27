<?php

final class PhabricatorEmbedFileRemarkupRule
  extends PhabricatorObjectRemarkupRule {

  const KEY_EMBED_FILE_PHIDS = 'phabricator.embedded-file-phids';

  protected function getObjectNamePrefix() {
    return 'F';
  }

  protected function loadObjects(array $ids) {
    $engine = $this->getEngine();

    $viewer = $engine->getConfig('viewer');
    $objects = id(new PhabricatorFileQuery())
      ->setViewer($viewer)
      ->withIDs($ids)
      ->execute();

    $phids_key = self::KEY_EMBED_FILE_PHIDS;
    $phids = $engine->getTextMetadata($phids_key, array());
    foreach (mpull($objects, 'getPHID') as $phid) {
      $phids[] = $phid;
    }
    $engine->setTextMetadata($phids_key, $phids);

    return $objects;
  }

  protected function renderObjectEmbed(
    $object,
    PhabricatorObjectHandle $handle,
    $options) {

    $options = $this->getFileOptions($options) + array(
      'name' => $object->getName(),
    );

    $is_viewable_image = $object->isViewableImage();
    $is_audio = $object->isAudio();
    $force_link = ($options['layout'] == 'link');

    $options['viewable'] = ($is_viewable_image || $is_audio);

    if ($is_viewable_image && !$force_link) {
      return $this->renderImageFile($object, $handle, $options);
    } else if ($is_audio && !$force_link) {
      return $this->renderAudioFile($object, $handle, $options);
    } else {
      return $this->renderFileLink($object, $handle, $options);
    }
  }

  private function getFileOptions($option_string) {
    $options = array(
      'size'    => null,
      'layout'  => 'left',
      'float'   => false,
      'width'   => null,
      'height'  => null,
      'alt' => null,
    );

    if ($option_string) {
      $option_string = trim($option_string, ', ');
      $parser = new PhutilSimpleOptions();
      $options = $parser->parse($option_string) + $options;
    }

    return $options;
  }

  private function renderImageFile(
    PhabricatorFile $file,
    PhabricatorObjectHandle $handle,
    array $options) {

    require_celerity_resource('lightbox-attachment-css');

    $attrs = array();
    $image_class = null;

    $use_size = true;
    if (!$options['size']) {
      $width = $this->parseDimension($options['width']);
      $height = $this->parseDimension($options['height']);
      if ($width || $height) {
        $use_size = false;
        $attrs += array(
          'src' => $file->getBestURI(),
          'width' => $width,
          'height' => $height,
        );
      }
    }

    if ($use_size) {
      switch ((string)$options['size']) {
        case 'full':
          $attrs += array(
            'src' => $file->getBestURI(),
            'height' => $file->getImageHeight(),
            'width' => $file->getImageWidth(),
          );
          $image_class = 'phabricator-remarkup-embed-image-full';
          break;
        case 'thumb':
        default:
          $preview_key = PhabricatorFileThumbnailTransform::TRANSFORM_PREVIEW;
          $xform = PhabricatorFileTransform::getTransformByKey($preview_key);
          $attrs['src'] = $file->getURIForTransform($xform);

          $dimensions = $xform->getTransformedDimensions($file);
          if ($dimensions) {
            list($x, $y) = $dimensions;
            $attrs['width'] = $x;
            $attrs['height'] = $y;
          }

          $image_class = 'phabricator-remarkup-embed-image';
          break;
      }
    }

    if (isset($options['alt'])) {
      $attrs['alt'] = $options['alt'];
    }

    $img = phutil_tag('img', $attrs);

    $embed = javelin_tag(
      'a',
      array(
        'href'        => $file->getBestURI(),
        'class'       => $image_class,
        'sigil'       => 'lightboxable',
        'meta'        => array(
          'phid'     => $file->getPHID(),
          'uri'      => $file->getBestURI(),
          'dUri'     => $file->getDownloadURI(),
          'viewable' => true,
        ),
      ),
      $img);

    switch ($options['layout']) {
      case 'right':
      case 'center':
      case 'inline':
      case 'left':
        $layout_class = 'phabricator-remarkup-embed-layout-'.$options['layout'];
        break;
      default:
        $layout_class = 'phabricator-remarkup-embed-layout-left';
        break;
    }

    if ($options['float']) {
      switch ($options['layout']) {
        case 'center':
        case 'inline':
          break;
        case 'right':
          $layout_class .= ' phabricator-remarkup-embed-float-right';
          break;
        case 'left':
        default:
          $layout_class .= ' phabricator-remarkup-embed-float-left';
          break;
      }
    }

    return phutil_tag(
      ($options['layout'] == 'inline' ? 'span' : 'div'),
      array(
        'class' => $layout_class,
      ),
      $embed);
  }

  private function renderAudioFile(
    PhabricatorFile $file,
    PhabricatorObjectHandle $handle,
    array $options) {

    if (idx($options, 'autoplay')) {
      $preload = 'auto';
      $autoplay = 'autoplay';
    } else {
      $preload = 'none';
      $autoplay = null;
    }

    return $this->newTag(
      'audio',
      array(
        'controls' => 'controls',
        'preload' => $preload,
        'autoplay' => $autoplay,
        'loop' => idx($options, 'loop') ? 'loop' : null,
      ),
      $this->newTag(
        'source',
        array(
          'src' => $file->getBestURI(),
          'type' => $file->getMimeType(),
        )));
  }

  private function renderFileLink(
    PhabricatorFile $file,
    PhabricatorObjectHandle $handle,
    array $options) {

    return id(new PhabricatorFileLinkView())
      ->setFilePHID($file->getPHID())
      ->setFileName($this->assertFlatText($options['name']))
      ->setFileDownloadURI($file->getDownloadURI())
      ->setFileViewURI($file->getBestURI())
      ->setFileViewable((bool)$options['viewable']);
  }

  private function parseDimension($string) {
    $string = trim($string);

    if (preg_match('/^(?:\d*\\.)?\d+%?$/', $string)) {
      return $string;
    }

    return null;
  }

}
