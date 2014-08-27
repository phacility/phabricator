<?php

abstract class PhabricatorObjectRemarkupRule extends PhutilRemarkupRule {

  const KEY_RULE_OBJECT = 'rule.object';
  const KEY_MENTIONED_OBJECTS = 'rule.object.mentioned';

  abstract protected function getObjectNamePrefix();
  abstract protected function loadObjects(array $ids);

  public function getPriority() {
    return 450.0;
  }

  protected function getObjectNamePrefixBeginsWithWordCharacter() {
    $prefix = $this->getObjectNamePrefix();
    return preg_match('/^\w/', $prefix);
  }

  protected function getObjectIDPattern() {
    return '[1-9]\d*';
  }

  protected function shouldMarkupObject(array $params) {
    return true;
  }

  protected function loadHandles(array $objects) {
    $phids = mpull($objects, 'getPHID');

    $handles = id(new PhabricatorHandleQuery($phids))
      ->withPHIDs($phids)
      ->setViewer($this->getEngine()->getConfig('viewer'))
      ->execute();

    $result = array();
    foreach ($objects as $id => $object) {
      $result[$id] = $handles[$object->getPHID()];
    }
    return $result;
  }

  protected function getObjectHref($object, $handle, $id) {
    return $handle->getURI();
  }

  protected function renderObjectRef($object, $handle, $anchor, $id) {
    $href = $this->getObjectHref($object, $handle, $id);
    $text = $this->getObjectNamePrefix().$id;

    if ($anchor) {
      $href = $href.'#'.$anchor;
      $text = $text.'#'.$anchor;
    }

    if ($this->getEngine()->isTextMode()) {
      return PhabricatorEnv::getProductionURI($href);
    }

    $status_closed = PhabricatorObjectHandleStatus::STATUS_CLOSED;

    $attr = array(
      'phid'    => $handle->getPHID(),
      'closed'  => ($handle->getStatus() == $status_closed),
    );

    return $this->renderHovertag($text, $href, $attr);
  }

  protected function renderObjectEmbed($object, $handle, $options) {
    $name = $handle->getFullName();
    $href = $handle->getURI();
    $status_closed = PhabricatorObjectHandleStatus::STATUS_CLOSED;

    if ($this->getEngine()->isTextMode()) {
      return $name.' <'.PhabricatorEnv::getProductionURI($href).'>';
    }

    $attr = array(
      'phid' => $handle->getPHID(),
      'closed'  => ($handle->getStatus() == $status_closed),
    );

    return $this->renderHovertag($name, $href, $attr);
  }

  protected function renderHovertag($name, $href, array $attr = array()) {
    return id(new PHUITagView())
      ->setName($name)
      ->setHref($href)
      ->setType(PHUITagView::TYPE_OBJECT)
      ->setPHID(idx($attr, 'phid'))
      ->setClosed(idx($attr, 'closed'))
      ->render();
  }

  public function apply($text) {
    $prefix = $this->getObjectNamePrefix();
    $prefix = preg_quote($prefix, '@');
    $id = $this->getObjectIDPattern();

    $text = preg_replace_callback(
      '@\B{'.$prefix.'('.$id.')((?:[^}\\\\]|\\\\.)*)}\B@',
      array($this, 'markupObjectEmbed'),
      $text);

    // If the prefix starts with a word character (like "D"), we want to
    // require a word boundary so that we don't match "XD1" as "D1". If the
    // prefix does not start with a word character, we want to require no word
    // boundary for the same reasons. Test if the prefix starts with a word
    // character.
    if ($this->getObjectNamePrefixBeginsWithWordCharacter()) {
      $boundary = '\\b';
    } else {
      $boundary = '\\B';
    }

    // NOTE: The "(?<!#)" prevents us from linking "#abcdef" or similar.
    // The "\b" allows us to link "(abcdef)" or similar without linking things
    // in the middle of words.

    $text = preg_replace_callback(
      '((?<!#)'.$boundary.$prefix.'('.$id.')(?:#([-\w\d]+))?\b)',
      array($this, 'markupObjectReference'),
      $text);

    return $text;
  }

  public function markupObjectEmbed($matches) {
    if (!$this->isFlatText($matches[0])) {
      return $matches[0];
    }

    return $this->markupObject(array(
      'type' => 'embed',
      'id' => $matches[1],
      'options' => idx($matches, 2),
      'original' => $matches[0],
    ));
  }

  public function markupObjectReference($matches) {
    if (!$this->isFlatText($matches[0])) {
      return $matches[0];
    }

    return $this->markupObject(array(
      'type' => 'ref',
      'id' => $matches[1],
      'anchor' => idx($matches, 2),
      'original' => $matches[0],
    ));
  }

  private function markupObject(array $params) {
    if (!$this->shouldMarkupObject($params)) {
      return $params['original'];
    }

    $regex = trim(
      PhabricatorEnv::getEnvConfig('remarkup.ignored-object-names'));
    if ($regex && preg_match($regex, $params['original'])) {
      return $params['original'];
    }

    $engine = $this->getEngine();
    $token = $engine->storeText('x');

    $metadata_key = self::KEY_RULE_OBJECT.'.'.$this->getObjectNamePrefix();
    $metadata = $engine->getTextMetadata($metadata_key, array());

    $metadata[] = array(
      'token'   => $token,
    ) + $params;

    $engine->setTextMetadata($metadata_key, $metadata);

    return $token;
  }

  public function didMarkupText() {
    $engine = $this->getEngine();
    $metadata_key = self::KEY_RULE_OBJECT.'.'.$this->getObjectNamePrefix();
    $metadata = $engine->getTextMetadata($metadata_key, array());

    if (!$metadata) {
      return;
    }


    $ids = ipull($metadata, 'id');
    $objects = $this->loadObjects($ids);

    // For objects that are invalid or which the user can't see, just render
    // the original text.

    // TODO: We should probably distinguish between these cases and render a
    // "you can't see this" state for nonvisible objects.

    foreach ($metadata as $key => $spec) {
      if (empty($objects[$spec['id']])) {
        $engine->overwriteStoredText(
          $spec['token'],
          $spec['original']);
        unset($metadata[$key]);
      }
    }

    $phids = $engine->getTextMetadata(self::KEY_MENTIONED_OBJECTS, array());
    foreach ($objects as $object) {
      $phids[$object->getPHID()] = $object->getPHID();
    }
    $engine->setTextMetadata(self::KEY_MENTIONED_OBJECTS, $phids);

    $handles = $this->loadHandles($objects);
    foreach ($metadata as $key => $spec) {
      $handle = $handles[$spec['id']];
      $object = $objects[$spec['id']];
      switch ($spec['type']) {
        case 'ref':
          $view = $this->renderObjectRef(
            $object,
            $handle,
            $spec['anchor'],
            $spec['id']);
          break;
        case 'embed':
          $spec['options'] = $this->assertFlatText($spec['options']);
          $view = $this->renderObjectEmbed($object, $handle, $spec['options']);
          break;
      }
      $engine->overwriteStoredText($spec['token'], $view);
    }

    $engine->setTextMetadata($metadata_key, array());
  }

}
