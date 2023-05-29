<?php

final class PhabricatorMemeEngine extends Phobject {

  private $viewer;
  private $template;
  private $aboveText;
  private $belowText;

  private $templateFile;
  private $metrics;

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public function getViewer() {
    return $this->viewer;
  }

  public function setTemplate($template) {
    $this->template = $template;
    return $this;
  }

  public function getTemplate() {
    return $this->template;
  }

  public function setAboveText($above_text) {
    $this->aboveText = $above_text;
    return $this;
  }

  public function getAboveText() {
    return $this->aboveText;
  }

  public function setBelowText($below_text) {
    $this->belowText = $below_text;
    return $this;
  }

  public function getBelowText() {
    return $this->belowText;
  }

  public function getGenerateURI() {
    $params = array(
      'macro' => $this->getTemplate(),
      'above' => $this->getAboveText(),
      'below' => $this->getBelowText(),
    );

    return new PhutilURI('/macro/meme/', $params);
  }

  public function newAsset() {
    $cache = $this->loadCachedFile();
    if ($cache) {
      return $cache;
    }

    $template = $this->loadTemplateFile();
    if (!$template) {
      throw new Exception(
        pht(
          'Template "%s" is not a valid template.',
          $template));
    }

    $hash = $this->newTransformHash();

    $asset = $this->newAssetFile($template);

    $xfile = id(new PhabricatorTransformedFile())
      ->setOriginalPHID($template->getPHID())
      ->setTransformedPHID($asset->getPHID())
      ->setTransform($hash);

    try {
      $caught = null;

      $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
      try {
        $xfile->save();
      } catch (Exception $ex) {
        $caught = $ex;
      }
      unset($unguarded);

      if ($caught) {
        throw $caught;
      }

      return $asset;
    } catch (AphrontDuplicateKeyQueryException $ex) {
      $xfile = $this->loadCachedFile();
      if (!$xfile) {
        throw $ex;
      }
      return $xfile;
    }
  }

  private function newTransformHash() {
    $properties = array(
      'kind' => 'meme',
      'above' => $this->getAboveText(),
      'below' => $this->getBelowText(),
    );

    $properties = phutil_json_encode($properties);

    return PhabricatorHash::digestForIndex($properties);
  }

  public function loadCachedFile() {
    $viewer = $this->getViewer();

    $template_file = $this->loadTemplateFile();
    if (!$template_file) {
      return null;
    }

    $hash = $this->newTransformHash();

    $xform = id(new PhabricatorTransformedFile())->loadOneWhere(
      'originalPHID = %s AND transform = %s',
      $template_file->getPHID(),
      $hash);
    if (!$xform) {
      return null;
    }

    return id(new PhabricatorFileQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($xform->getTransformedPHID()))
      ->executeOne();
  }

  private function loadTemplateFile() {
    if ($this->templateFile === null) {
      $viewer = $this->getViewer();
      $template = $this->getTemplate();

      $macro = id(new PhabricatorMacroQuery())
        ->setViewer($viewer)
        ->withNames(array($template))
        ->needFiles(true)
        ->executeOne();
      if (!$macro) {
        return null;
      }

      $this->templateFile = $macro->getFile();
    }

    return $this->templateFile;
  }

  private function newAssetFile(PhabricatorFile $template) {
    $data = $this->newAssetData($template);
    return PhabricatorFile::newFromFileData(
      $data,
      array(
        'name' => 'meme-'.$template->getName(),
        'canCDN' => true,

        // In modern code these can end up linked directly in email, so let
        // them stick around for a while.
        'ttl.relative' => phutil_units('30 days in seconds'),
      ));
  }

  private function newAssetData(PhabricatorFile $template) {
    $template_data = $template->loadFileData();

    // When we aren't adding text, just return the data unmodified. This saves
    // us from doing expensive stitching when we aren't actually making any
    // changes to the image.
    $above_text = coalesce($this->getAboveText(), '');
    $below_text = coalesce($this->getBelowText(), '');
    if (!strlen(trim($above_text)) && !strlen(trim($below_text))) {
      return $template_data;
    }

    $result = $this->newImagemagickAsset($template, $template_data);
    if ($result) {
      return $result;
    }

    return $this->newGDAsset($template, $template_data);
  }

  private function newImagemagickAsset(
    PhabricatorFile $template,
    $template_data) {

    // We're only going to use Imagemagick on GIFs.
    $mime_type = $template->getMimeType();
    if ($mime_type != 'image/gif') {
      return null;
    }

    // We're only going to use Imagemagick if it is actually available.
    $available = PhabricatorEnv::getEnvConfig('files.enable-imagemagick');
    if (!$available) {
      return null;
    }

    // Test of the GIF is an animated GIF. If it's a flat GIF, we'll fall
    // back to GD.
    $input = new TempFile();
    Filesystem::writeFile($input, $template_data);
    list($err, $out) = exec_manual('convert %s info:', $input);
    if ($err) {
      return null;
    }

    $split = phutil_split_lines($out);
    $frames = count($split);
    if ($frames <= 1) {
      return null;
    }

    // Split the frames apart, transform each frame, then merge them back
    // together.
    $output = new TempFile();

    $future = new ExecFuture(
      'convert %s -coalesce +adjoin %s_%s',
      $input,
      $input,
      '%09d');
    $future->setTimeout(10)->resolvex();

    $output_files = array();
    for ($ii = 0; $ii < $frames; $ii++) {
      $frame_name = sprintf('%s_%09d', $input, $ii);
      $output_name = sprintf('%s_%09d', $output, $ii);

      $output_files[] = $output_name;

      $frame_data = Filesystem::readFile($frame_name);
      $memed_frame_data = $this->newGDAsset($template, $frame_data);
      Filesystem::writeFile($output_name, $memed_frame_data);
    }

    $future = new ExecFuture(
      'convert -dispose background -loop 0 %Ls %s',
      $output_files,
      $output);
    $future->setTimeout(10)->resolvex();

    return Filesystem::readFile($output);
  }

  private function newGDAsset(PhabricatorFile $template, $data) {
    $img = imagecreatefromstring($data);
    if (!$img) {
      throw new Exception(
        pht('Failed to imagecreatefromstring() image template data.'));
    }

    $dx = imagesx($img);
    $dy = imagesy($img);

    $metrics = $this->getMetrics($dx, $dy);
    $font = $this->getFont();
    $size = $metrics['size'];

    $above = coalesce($this->getAboveText(), '');
    if (strlen($above)) {
      $x = (int)floor(($dx - $metrics['text']['above']['width']) / 2);
      $y = $metrics['text']['above']['height'] + 12;

      $this->drawText($img, $font, $metrics['size'], $x, $y, $above);
    }

    $below = coalesce($this->getBelowText(), '');
    if (strlen($below)) {
      $x = (int)floor(($dx - $metrics['text']['below']['width']) / 2);
      $y = $dy - 12 - $metrics['text']['below']['descend'];

      $this->drawText($img, $font, $metrics['size'], $x, $y, $below);
    }

    return PhabricatorImageTransformer::saveImageDataInAnyFormat(
      $img,
      $template->getMimeType());
  }

  private function getFont() {
    $phabricator_root = dirname(phutil_get_library_root('phabricator'));

    $font_root = $phabricator_root.'/resources/font/';
    if (Filesystem::pathExists($font_root.'impact.ttf')) {
      $font_path = $font_root.'impact.ttf';
    } else {
      $font_path = $font_root.'tuffy.ttf';
    }

    return $font_path;
  }

  private function getMetrics($dim_x, $dim_y) {
    if ($this->metrics === null) {
      $font = $this->getFont();

      $font_max = 72;
      $font_min = 5;

      $margin_x = 16;
      $margin_y = 16;

      $last = null;
      $cursor = floor(($font_max + $font_min) / 2);
      $min = $font_min;
      $max = $font_max;

      $texts = array(
        'above' => $this->getAboveText(),
        'below' => $this->getBelowText(),
      );

      $metrics = null;
      $best = null;
      while (true) {
        $all_fit = true;
        $text_metrics = array();
        foreach ($texts as $key => $text) {
          $text = coalesce($text, '');
          $box = imagettfbbox($cursor, 0, $font, $text);
          $height = abs($box[3] - $box[5]);
          $width = abs($box[0] - $box[2]);

          // This is the number of pixels below the baseline that the
          // text extends, for example if it has a "y".
          $descend = $box[3];

          if (($height + $margin_y) > $dim_y) {
            $all_fit = false;
            break;
          }

          if (($width + $margin_x) > $dim_x) {
            $all_fit = false;
            break;
          }

          $text_metrics[$key]['width'] = $width;
          $text_metrics[$key]['height'] = $height;
          $text_metrics[$key]['descend'] = $descend;
        }

        if ($all_fit || $best === null) {
          $best = $cursor;
          $metrics = $text_metrics;
        }

        if ($all_fit) {
          $min = $cursor;
        } else {
          $max = $cursor;
        }

        $last = $cursor;
        $cursor = floor(($max + $min) / 2);
        if ($cursor === $last) {
          break;
        }
      }

      $this->metrics = array(
        'size' => $best,
        'text' => $metrics,
      );
    }

    return $this->metrics;
  }

  private function drawText($img, $font, $size, $x, $y, $text) {
    $text_color = imagecolorallocate($img, 255, 255, 255);
    $border_color = imagecolorallocate($img, 0, 0, 0);

    $border = 2;
    for ($xx = ($x - $border); $xx <= ($x + $border); $xx += $border) {
      for ($yy = ($y - $border); $yy <= ($y + $border); $yy += $border) {
        if (($xx === $x) && ($yy === $y)) {
          continue;
        }
        imagettftext($img, $size, 0, $xx, $yy, $border_color, $font, $text);
      }
    }

    imagettftext($img, $size, 0, $x, $y, $text_color, $font, $text);
  }


}
