<?php

/**
 * @group markup
 */
abstract class PhabricatorRemarkupRuleObject
  extends PhutilRemarkupRule {

  const KEY_RULE_OBJECT = 'rule.object';

  abstract protected function getObjectNamePrefix();
  abstract protected function loadObjects(array $ids);

  protected function getObjectIDPattern() {
    return '[1-9]\d*';
  }

  protected function shouldMarkupObject(array $params) {
    return true;
  }

  protected function loadHandles(array $objects) {
    $phids = mpull($objects, 'getPHID');
    $query = new PhabricatorObjectHandleData($phids);

    $viewer = $this->getEngine()->getConfig('viewer');
    $query->setViewer($viewer);
    $handles = $query->loadHandles();

    $result = array();
    foreach ($objects as $id => $object) {
      $result[$id] = $handles[$object->getPHID()];
    }
    return $result;
  }

  protected function renderObjectRef($object, $handle, $anchor, $id) {
    $href = $handle->getURI();
    $text = $this->getObjectNamePrefix().$id;
    if ($anchor) {
      $matches = null;
      if (preg_match('@^(?:comment-)?(\d{1,7})$@', $anchor, $matches)) {
        // Maximum length is 7 because 12345678 could be a file hash in
        // Differential.
        $href = $href.'#comment-'.$matches[1];
        $text = $text.'#'.$matches[1];
      } else {
        $href = $href.'#'.$anchor;
        $text = $text.'#'.$anchor;
      }
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
    $attr = array(
      'phid' => $handle->getPHID(),
    );

    return $this->renderHovertag($name, $href, $attr);
  }

  protected function renderHovertag($name, $href, array $attr = array()) {
    return id(new PhabricatorTagView())
      ->setName($name)
      ->setHref($href)
      ->setType(PhabricatorTagView::TYPE_OBJECT)
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

    // NOTE: The "(?<!#)" prevents us from linking "#abcdef" or similar. The
    // "\b" allows us to link "(abcdef)" or similar without linking things
    // in the middle of words.
    $text = preg_replace_callback(
      '@(?<!#)\b'.$prefix.'('.$id.')(?:#([-\w\d]+))?\b@',
      array($this, 'markupObjectReference'),
      $text);

    return $text;
  }

  public function markupObjectEmbed($matches) {
    return $this->markupObject(array(
      'type' => 'embed',
      'id' => $matches[1],
      'options' => idx($matches, 2),
      'original' => $matches[0],
    ));
  }

  public function markupObjectReference($matches) {
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
          $view = $this->renderObjectEmbed($object, $handle, $spec['options']);
          break;
      }
      $engine->overwriteStoredText($spec['token'], $view);
    }

    $engine->setTextMetadata($metadata_key, array());
  }

}
