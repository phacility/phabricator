<?php

/**
 * @group markup
 */
final class PhabricatorRemarkupRuleImageMacro
  extends PhutilRemarkupRule {

  private $images;

  public function apply($text) {
    return preg_replace_callback(
      '@^([a-zA-Z0-9:_\-]+)$@m',
      array($this, 'markupImageMacro'),
      $text);
  }

  public function markupImageMacro($matches) {
    if ($this->images === null) {
      $this->images = array();

      $viewer = $this->getEngine()->getConfig('viewer');
      $rows = id(new PhabricatorMacroQuery())
        ->setViewer($viewer)
        ->withStatus(PhabricatorMacroQuery::STATUS_ACTIVE)
        ->execute();
      foreach ($rows as $row) {
        $spec = array(
          'image' => $row->getFilePHID(),
        );

        $behavior_none = PhabricatorFileImageMacro::AUDIO_BEHAVIOR_NONE;
        if ($row->getAudioPHID()) {
          if ($row->getAudioBehavior() != $behavior_none) {
            $spec += array(
              'audio' => $row->getAudioPHID(),
              'audioBehavior' => $row->getAudioBehavior(),
            );
          }
        }

        $this->images[$row->getName()] = $spec;
      }
    }

    $name = (string)$matches[1];

    if (array_key_exists($name, $this->images)) {
      $phid = $this->images[$name]['image'];

      $file = id(new PhabricatorFile())->loadOneWhere('phid = %s', $phid);

      if ($this->getEngine()->isTextMode()) {
        if ($file) {
          $name .= ' <'.$file->getBestURI().'>';
        }
        return $this->getEngine()->storeText($name);
      }

      $style = null;
      $src_uri = null;
      if ($file) {
        $src_uri = $file->getBestURI();
        $file_data = $file->getMetadata();
        $height = idx($file_data, PhabricatorFile::METADATA_IMAGE_HEIGHT);
        $width = idx($file_data, PhabricatorFile::METADATA_IMAGE_WIDTH);
        if ($height && $width) {
          $style = sprintf(
            'height: %dpx; width: %dpx;',
            $height,
            $width);
        }
      }

      $id = null;
      $audio_phid = idx($this->images[$name], 'audio');
      if ($audio_phid) {
        $id = celerity_generate_unique_node_id();

        $loop = null;
        switch (idx($this->images[$name], 'audioBehavior')) {
          case PhabricatorFileImageMacro::AUDIO_BEHAVIOR_LOOP:
            $loop = true;
            break;
        }

        $file = id(new PhabricatorFile())->loadOneWhere(
          'phid = %s',
          $audio_phid);
        if ($file) {
          Javelin::initBehavior(
            'audio-source',
            array(
              'sourceID' => $id,
              'audioURI' => $file->getBestURI(),
              'loop' => $loop,
            ));
        }
      }

      $img = phutil_tag(
        'img',
        array(
          'id'    => $id,
          'src'   => $src_uri,
          'alt'   => $matches[1],
          'title' => $matches[1],
          'style' => $style,
        ));

      return $this->getEngine()->storeText($img);
    } else {
      return $matches[1];
    }
  }

}
