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

    $viewer = $this->getEngine()->getConfig('viewer');
    $handles = $viewer->loadHandles($phids);
    $handles = iterator_to_array($handles);

    $result = array();
    foreach ($objects as $id => $object) {
      $result[$id] = $handles[$object->getPHID()];
    }
    return $result;
  }

  protected function getObjectHref(
    $object,
    PhabricatorObjectHandle $handle,
    $id) {

    $uri = $handle->getURI();

    if ($this->getEngine()->getConfig('uri.full')) {
      $uri = PhabricatorEnv::getURI($uri);
    }

    return $uri;
  }

  protected function renderObjectRefForAnyMedia (
    $object,
    PhabricatorObjectHandle $handle,
    $anchor,
    $id) {

    $href = $this->getObjectHref($object, $handle, $id);
    $text = $this->getObjectNamePrefix().$id;

    if ($anchor) {
      $href = $href.'#'.$anchor;
      $text = $text.'#'.$anchor;
    }

    if ($this->getEngine()->isTextMode()) {
      return PhabricatorEnv::getProductionURI($href);
    } else if ($this->getEngine()->isHTMLMailMode()) {
      $href = PhabricatorEnv::getProductionURI($href);
      return $this->renderObjectTagForMail($text, $href, $handle);
    }

    return $this->renderObjectRef($object, $handle, $anchor, $id);

  }

  protected function renderObjectRef(
    $object,
    PhabricatorObjectHandle $handle,
    $anchor,
    $id) {

    $href = $this->getObjectHref($object, $handle, $id);
    $text = $this->getObjectNamePrefix().$id;
    $status_closed = PhabricatorObjectHandle::STATUS_CLOSED;

    if ($anchor) {
      $href = $href.'#'.$anchor;
      $text = $text.'#'.$anchor;
    }

    $attr = array(
      'phid'    => $handle->getPHID(),
      'closed'  => ($handle->getStatus() == $status_closed),
    );

    return $this->renderHovertag($text, $href, $attr);
  }

  protected function renderObjectEmbedForAnyMedia(
    $object,
    PhabricatorObjectHandle $handle,
    $options) {

    $name = $handle->getFullName();
    $href = $handle->getURI();

    if ($this->getEngine()->isTextMode()) {
      return $name.' <'.PhabricatorEnv::getProductionURI($href).'>';
    } else if ($this->getEngine()->isHTMLMailMode()) {
      $href = PhabricatorEnv::getProductionURI($href);
      return $this->renderObjectTagForMail($name, $href, $handle);
    }

    return $this->renderObjectEmbed($object, $handle, $options);
  }

  protected function renderObjectEmbed(
    $object,
    PhabricatorObjectHandle $handle,
    $options) {

    $name = $handle->getFullName();
    $href = $handle->getURI();
    $status_closed = PhabricatorObjectHandle::STATUS_CLOSED;
    $attr = array(
      'phid' => $handle->getPHID(),
      'closed'  => ($handle->getStatus() == $status_closed),
    );

    return $this->renderHovertag($name, $href, $attr);
  }

  protected function renderObjectTagForMail(
    $text,
    $href,
    PhabricatorObjectHandle $handle) {

    $status_closed = PhabricatorObjectHandle::STATUS_CLOSED;
    $strikethrough = $handle->getStatus() == $status_closed ?
      'text-decoration: line-through;' :
      'text-decoration: none;';

    return phutil_tag(
      'a',
      array(
        'href' => $href,
        'style' => 'background-color: #e7e7e7;
          border-color: #e7e7e7;
          border-radius: 3px;
          padding: 0 4px;
          font-weight: bold;
          color: black;'
          .$strikethrough,
      ),
      $text);
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
    $text = preg_replace_callback(
      $this->getObjectEmbedPattern(),
      array($this, 'markupObjectEmbed'),
      $text);

    $text = preg_replace_callback(
      $this->getObjectReferencePattern(),
      array($this, 'markupObjectReference'),
      $text);

    return $text;
  }

  private function getObjectEmbedPattern() {
    $prefix = $this->getObjectNamePrefix();
    $prefix = preg_quote($prefix);
    $id = $this->getObjectIDPattern();

    return '(\B{'.$prefix.'('.$id.')([,\s](?:[^}\\\\]|\\\\.)*)?}\B)u';
  }

  private function getObjectReferencePattern() {
    $prefix = $this->getObjectNamePrefix();
    $prefix = preg_quote($prefix);

    $id = $this->getObjectIDPattern();

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

    // The "(?<![#-])" prevents us from linking "#abcdef" or similar, and
    // "ABC-T1" (see T5714).

    // The "\b" allows us to link "(abcdef)" or similar without linking things
    // in the middle of words.

    return '((?<![#-])'.$boundary.$prefix.'('.$id.')(?:#([-\w\d]+))?(?!\w))u';
  }


  /**
   * Extract matched object references from a block of text.
   *
   * This is intended to make it easy to write unit tests for object remarkup
   * rules. Production code is not normally expected to call this method.
   *
   * @param   string  Text to match rules against.
   * @return  wild    Matches, suitable for writing unit tests against.
   */
  public function extractReferences($text) {
    $embed_matches = null;
    preg_match_all(
      $this->getObjectEmbedPattern(),
      $text,
      $embed_matches,
      PREG_OFFSET_CAPTURE | PREG_SET_ORDER);

    $ref_matches = null;
    preg_match_all(
      $this->getObjectReferencePattern(),
      $text,
      $ref_matches,
      PREG_OFFSET_CAPTURE | PREG_SET_ORDER);

    $results = array();
    $sets = array(
      'embed' => $embed_matches,
      'ref' => $ref_matches,
    );
    foreach ($sets as $type => $matches) {
      $formatted = array();
      foreach ($matches as $match) {
        $format = array(
          'offset' => $match[1][1],
          'id' => $match[1][0],
        );
        if (isset($match[2][0])) {
          $format['tail'] = $match[2][0];
        }
        $formatted[] = $format;
      }
      $results[$type] = $formatted;
    }

    return $results;
  }

  public function markupObjectEmbed(array $matches) {
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

  public function markupObjectReference(array $matches) {
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

          $view = $this->renderObjectRefForAnyMedia(
            $object,
            $handle,
            $spec['anchor'],
            $spec['id']);
          break;
        case 'embed':
          $spec['options'] = $this->assertFlatText($spec['options']);
          $view = $this->renderObjectEmbedForAnyMedia(
            $object,
            $handle,
            $spec['options']);
          break;
      }
      $engine->overwriteStoredText($spec['token'], $view);
    }

    $engine->setTextMetadata($metadata_key, array());
  }

}
