<?php

final class PhabricatorHandleRemarkupRule extends PhutilRemarkupRule {

  const KEY_RULE_HANDLE = 'rule.handle';
  const KEY_RULE_HANDLE_ORIGINAL = 'rule.handle.original';

  public function apply($text) {
    return preg_replace_callback(
      '/{(PHID-[a-zA-Z0-9-]*)}/',
      array($this, 'markupHandle'),
      $text);
  }

  public function markupHandle(array $matches) {
    $engine = $this->getEngine();
    $viewer = $engine->getConfig('viewer');

    if (!$this->isFlatText($matches[0])) {
      return $matches[0];
    }

    $phid_type = phid_get_type($matches[1]);
    if ($phid_type == PhabricatorPHIDConstants::PHID_TYPE_UNKNOWN) {
      return $matches[0];
    }

    $token = $engine->storeText($matches[0]);
    if ($engine->isTextMode()) {
      return $token;
    }

    $original_key = self::KEY_RULE_HANDLE_ORIGINAL;
    $original = $engine->getTextMetadata($original_key, array());
    $original[$token] = $matches[0];
    $engine->setTextMetadata($original_key, $original);

    $metadata_key = self::KEY_RULE_HANDLE;
    $metadata = $engine->getTextMetadata($metadata_key, array());
    $phid = $matches[1];
    if (empty($metadata[$phid])) {
      $metadata[$phid] = array();
    }
    $metadata[$phid][] = $token;
    $engine->setTextMetadata($metadata_key, $metadata);

    return $token;
  }

  public function didMarkupText() {
    $engine = $this->getEngine();

    $metadata_key = self::KEY_RULE_HANDLE;

    $metadata = $engine->getTextMetadata($metadata_key, array());
    if (empty($metadata)) {
      // No mentions, or we already processed them.
      return;
    }

    $original_key = self::KEY_RULE_HANDLE_ORIGINAL;
    $original = $engine->getTextMetadata($original_key, array());

    $phids = array_keys($metadata);

    $handles = id(new PhabricatorHandleQuery())
      ->setViewer($this->getEngine()->getConfig('viewer'))
      ->withPHIDs($phids)
      ->execute();

    foreach ($metadata as $phid => $tokens) {
      $handle = idx($handles, $phid);

      if ($handle->isComplete()) {
        if ($engine->isHTMLMailMode()) {
          $href = $handle->getURI();
          $href = PhabricatorEnv::getProductionURI($href);

          $link = phutil_tag(
            'a',
            array(
              'href' => $href,
              'style' => '
                border-color: #f1f7ff;
                color: #19558d;
                background-color: #f1f7ff;
                border: 1px solid transparent;
                border-radius: 3px;
                font-weight: bold;
                padding: 0 4px;',
            ),
            $handle->getLinkName());
        } else {
          $link = $handle->renderTag();
          $link->setPHID($phid);
        }
        foreach ($tokens as $token) {
          $engine->overwriteStoredText($token, $link);
        }
      } else {
        foreach ($tokens as $token) {
          $engine->overwriteStoredText($token, idx($original, $token));
        }
      }
    }

    $engine->setTextMetadata($metadata_key, array());
  }
}
