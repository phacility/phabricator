<?php

final class PhabricatorEmbedFileRemarkupRule
  extends PhabricatorObjectRemarkupRule {

  private $viewer;

  const KEY_ATTACH_INTENT_FILE_PHIDS = 'files.attach-intent';

  protected function getObjectNamePrefix() {
    return 'F';
  }

  protected function loadObjects(array $ids) {
    $engine = $this->getEngine();

    $this->viewer = $engine->getConfig('viewer');
    $objects = id(new PhabricatorFileQuery())
      ->setViewer($this->viewer)
      ->withIDs($ids)
      ->needTransforms(
        array(
          PhabricatorFileThumbnailTransform::TRANSFORM_PREVIEW,
        ))
      ->execute();
    $objects = mpull($objects, null, 'getID');


    // Identify files embedded in the block with "attachment intent", i.e.
    // those files which the user appears to want to attach to the object.
    // Files referenced inside quoted blocks are not considered to have this
    // attachment intent.

    $metadata_key = self::KEY_RULE_OBJECT.'.'.$this->getObjectNamePrefix();
    $metadata = $engine->getTextMetadata($metadata_key, array());

    $attach_key = self::KEY_ATTACH_INTENT_FILE_PHIDS;
    $attach_phids = $engine->getTextMetadata($attach_key, array());

    foreach ($metadata as $item) {

      // If this reference was inside a quoted block, don't count it. Quoting
      // someone else doesn't establish an intent to attach a file.
      $depth = idx($item, 'quote.depth');
      if ($depth > 0) {
        continue;
      }

      $id = $item['id'];
      $file = idx($objects, $id);

      if (!$file) {
        continue;
      }

      $attach_phids[] = $file->getPHID();
    }

    $attach_phids = array_fuse($attach_phids);
    $attach_phids = array_keys($attach_phids);

    $engine->setTextMetadata($attach_key, $attach_phids);


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
    $is_video = $object->isVideo();
    $force_link = ($options['layout'] == 'link');

    // If a file is both audio and video, as with "application/ogg" by default,
    // render it as video but allow the user to specify `media=audio` if they
    // want to force it to render as audio.
    if ($is_audio && $is_video) {
      $media = $options['media'];
      if ($media == 'audio') {
        $is_video = false;
      } else {
        $is_audio = false;
      }
    }

    $options['viewable'] = ($is_viewable_image || $is_audio || $is_video);

    if ($is_viewable_image && !$force_link) {
      return $this->renderImageFile($object, $handle, $options);
    } else if ($is_video && !$force_link) {
      return $this->renderVideoFile($object, $handle, $options);
    } else if ($is_audio && !$force_link) {
      return $this->renderAudioFile($object, $handle, $options);
    } else {
      return $this->renderFileLink($object, $handle, $options);
    }
  }

  private function getFileOptions($option_string) {
    $options = array(
      'size' => null,
      'layout' => 'left',
      'float' => false,
      'width' => null,
      'height' => null,
      'alt' => null,
      'media' => null,
      'autoplay' => null,
      'loop' => null,
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

    require_celerity_resource('phui-lightbox-css');

    $attrs = array();
    $image_class = 'phabricator-remarkup-embed-image';

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
        // Displays "full" in normal Remarkup, "wide" in Documents
        case 'wide':
          $attrs += array(
            'src' => $file->getBestURI(),
            'width' => $file->getImageWidth(),
          );
          $image_class = 'phabricator-remarkup-embed-image-wide';
          break;
        case 'thumb':
        default:
          $preview_key = PhabricatorFileThumbnailTransform::TRANSFORM_PREVIEW;
          $xform = PhabricatorFileTransform::getTransformByKey($preview_key);

          $existing_xform = $file->getTransform($preview_key);
          if ($existing_xform) {
            $xform_uri = $existing_xform->getCDNURI('data');
          } else {
            $xform_uri = $file->getURIForTransform($xform);
          }

          $attrs['src'] = $xform_uri;

          $dimensions = $xform->getTransformedDimensions($file);
          if ($dimensions) {
            list($x, $y) = $dimensions;
            $attrs['width'] = $x;
            $attrs['height'] = $y;
          }
          break;
      }
    }

    $alt = null;
    if (isset($options['alt'])) {
      $alt = $options['alt'];
    }

    if ($alt === null || !strlen($alt)) {
      $alt = $file->getAltText();
    }

    $attrs['alt'] = $alt;

    $img = phutil_tag('img', $attrs);

    $embed = javelin_tag(
      'a',
      array(
        'href'        => $file->getBestURI(),
        'class'       => $image_class,
        'sigil'       => 'lightboxable',
        'meta'        => array(
          'phid' => $file->getPHID(),
          'uri' => $file->getBestURI(),
          'dUri' => $file->getDownloadURI(),
          'alt' => $alt,
          'viewable' => true,
          'monogram' => $file->getMonogram(),
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
    return $this->renderMediaFile('audio', $file, $handle, $options);
  }

  private function renderVideoFile(
    PhabricatorFile $file,
    PhabricatorObjectHandle $handle,
    array $options) {
    return $this->renderMediaFile('video', $file, $handle, $options);
  }

  private function renderMediaFile(
    $tag,
    PhabricatorFile $file,
    PhabricatorObjectHandle $handle,
    array $options) {

    $is_video = ($tag == 'video');

    if (idx($options, 'autoplay')) {
      $preload = 'auto';
      $autoplay = 'autoplay';
    } else {
      // If we don't preload video, the user can't see the first frame and
      // has no clue what they're looking at, so always preload.
      if ($is_video) {
        $preload = 'auto';
      } else {
        $preload = 'none';
      }
      $autoplay = null;
    }

    // Rendering contexts like feed can disable autoplay.
    $engine = $this->getEngine();
    if ($engine->getConfig('autoplay.disable')) {
      $autoplay = null;
    }

    if ($is_video) {
      // See T13135. Chrome refuses to play videos with type "video/quicktime",
      // even though it may actually be able to play them. The least awful fix
      // based on available information is to simply omit the "type" attribute
      // from `<source />` tags. This causes Chrome to try to play the video
      // and realize it can, and does not appear to produce any bad behavior in
      // any other browser.
      $mime_type = null;
    } else {
      $mime_type = $file->getMimeType();
    }

    return $this->newTag(
      $tag,
      array(
        'controls' => 'controls',
        'preload' => $preload,
        'autoplay' => $autoplay,
        'loop' => idx($options, 'loop') ? 'loop' : null,
        'alt' => $options['alt'],
        'class' => 'phabricator-media',
      ),
      $this->newTag(
        'source',
        array(
          'src' => $file->getBestURI(),
          'type' => $mime_type,
        )));
  }

  private function renderFileLink(
    PhabricatorFile $file,
    PhabricatorObjectHandle $handle,
    array $options) {

    return id(new PhabricatorFileLinkView())
      ->setViewer($this->viewer)
      ->setFilePHID($file->getPHID())
      ->setFileName($this->assertFlatText($options['name']))
      ->setFileDownloadURI($file->getDownloadURI())
      ->setFileViewURI($file->getBestURI())
      ->setFileViewable((bool)$options['viewable'])
      ->setFileSize(phutil_format_bytes($file->getByteSize()))
      ->setFileMonogram($file->getMonogram());
  }

  private function parseDimension($string) {
    if ($string === null || !strlen($string)) {
      return null;
    }
    $string = trim($string);

    if (preg_match('/^(?:\d*\\.)?\d+%?$/', $string)) {
      return $string;
    }

    return null;
  }

}
