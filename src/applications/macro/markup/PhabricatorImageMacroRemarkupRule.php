<?php

final class PhabricatorImageMacroRemarkupRule extends PhutilRemarkupRule {

  private $macros;

  const KEY_RULE_MACRO = 'rule.macro';

  public function apply($text) {
    return preg_replace_callback(
      '@^\s*([a-zA-Z0-9:_\-]+)$@m',
      array($this, 'markupImageMacro'),
      $text);
  }

  public function markupImageMacro(array $matches) {
    if ($this->macros === null) {
      $this->macros = array();

      $viewer = $this->getEngine()->getConfig('viewer');
      $rows = id(new PhabricatorMacroQuery())
        ->setViewer($viewer)
        ->withStatus(PhabricatorMacroQuery::STATUS_ACTIVE)
        ->execute();

      $this->macros = mpull($rows, 'getPHID', 'getName');
    }

    $name = (string)$matches[1];
    if (empty($this->macros[$name])) {
      return $matches[1];
    }

    $engine = $this->getEngine();


    $metadata_key = self::KEY_RULE_MACRO;
    $metadata = $engine->getTextMetadata($metadata_key, array());

    $token = $engine->storeText('<macro>');
    $metadata[] = array(
      'token' => $token,
      'phid' => $this->macros[$name],
      'original' => $name,
    );

    $engine->setTextMetadata($metadata_key, $metadata);

    return $token;
  }

  public function didMarkupText() {
    $engine = $this->getEngine();
    $metadata_key = self::KEY_RULE_MACRO;
    $metadata = $engine->getTextMetadata($metadata_key, array());

    if (!$metadata) {
      return;
    }

    $phids = ipull($metadata, 'phid');
    $viewer = $this->getEngine()->getConfig('viewer');

    // Load all the macros.
    $macros = id(new PhabricatorMacroQuery())
      ->setViewer($viewer)
      ->withStatus(PhabricatorMacroQuery::STATUS_ACTIVE)
      ->withPHIDs($phids)
      ->execute();
    $macros = mpull($macros, null, 'getPHID');

    // Load all the images and audio.
    $file_phids = array_merge(
      array_values(mpull($macros, 'getFilePHID')),
      array_values(mpull($macros, 'getAudioPHID')));

    $file_phids = array_filter($file_phids);

    $files = array();
    if ($file_phids) {
      $files = id(new PhabricatorFileQuery())
        ->setViewer($viewer)
        ->withPHIDs($file_phids)
        ->execute();
      $files = mpull($files, null, 'getPHID');
    }

    // Replace any macros that we couldn't load the macro or image for with
    // the original text.
    foreach ($metadata as $key => $spec) {
      $macro = idx($macros, $spec['phid']);
      if ($macro) {
        $file = idx($files, $macro->getFilePHID());
        if ($file) {
          continue;
        }
      }

      $engine->overwriteStoredText($spec['token'], $spec['original']);
      unset($metadata[$key]);
    }

    foreach ($metadata as $spec) {
      $macro = $macros[$spec['phid']];
      $file = $files[$macro->getFilePHID()];
      $src_uri = $file->getBestURI();

      if ($this->getEngine()->isTextMode()) {
        $result = $spec['original'].' <'.$src_uri.'>';
        $engine->overwriteStoredText($spec['token'], $result);
        continue;
      } else if ($this->getEngine()->isHTMLMailMode()) {
        $src_uri = PhabricatorEnv::getProductionURI($src_uri);
      }

      $id = null;
      $audio = idx($files, $macro->getAudioPHID());
      $should_play = ($audio && $macro->getAudioBehavior() !=
        PhabricatorFileImageMacro::AUDIO_BEHAVIOR_NONE);
      if ($should_play) {
        $id = celerity_generate_unique_node_id();

        $loop = null;
        switch ($macro->getAudioBehavior()) {
          case PhabricatorFileImageMacro::AUDIO_BEHAVIOR_LOOP:
            $loop = true;
            break;
        }

        Javelin::initBehavior(
          'audio-source',
          array(
            'sourceID' => $id,
            'audioURI' => $audio->getBestURI(),
            'loop' => $loop,
          ));
      }

      $result = $this->newTag(
        'img',
        array(
          'id'    => $id,
          'src'   => $src_uri,
          'alt'   => $spec['original'],
          'title' => $spec['original'],
          'height' => $file->getImageHeight(),
          'width' => $file->getImageWidth(),
          'class' => 'phabricator-remarkup-macro',
        ));

      $engine->overwriteStoredText($spec['token'], $result);
    }

    $engine->setTextMetadata($metadata_key, array());
  }

}
