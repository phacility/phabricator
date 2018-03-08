<?php

final class PhabricatorMemeEngine extends Phobject {

  private $viewer;
  private $template;
  private $aboveText;
  private $belowText;

  private $templateFile;

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
    return id(new PhutilURI('/macro/meme/'))
      ->alter('macro', $this->getTemplate())
      ->alter('above', $this->getAboveText())
      ->alter('below', $this->getBelowText());
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

    $transformer = new PhabricatorImageTransformer();
    $asset = $transformer->executeMemeTransform(
      $template,
      $this->getAboveText(),
      $this->getBelowText());

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
      'above' => phutil_utf8_strtoupper($this->getAboveText()),
      'below' => phutil_utf8_strtoupper($this->getBelowText()),
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

}
