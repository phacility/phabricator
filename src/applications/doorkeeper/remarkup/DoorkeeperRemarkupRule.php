<?php

abstract class DoorkeeperRemarkupRule extends PhutilRemarkupRule {

  const KEY_TAGS = 'doorkeeper.tags';

  public function getPriority() {
    return 350.0;
  }

  protected function addDoorkeeperTag(array $spec) {
    $key = self::KEY_TAGS;
    $engine = $this->getEngine();
    $token = $engine->storeText(get_class($this));

    $tags = $engine->getTextMetadata($key, array());

    $tags[] = array(
      'token' => $token,
    ) + $spec + array(
      'extra' => array(),
    );

    $engine->setTextMetadata($key, $tags);
    return $token;
  }

  public function didMarkupText() {
    $key = self::KEY_TAGS;
    $engine = $this->getEngine();
    $tags = $engine->getTextMetadata($key, array());

    if (!$tags) {
      return;
    }

    $refs = array();
    foreach ($tags as $spec) {
      $tag_id = celerity_generate_unique_node_id();

      $refs[] = array(
        'id' => $tag_id,
      ) + $spec['tag'];

      if ($this->getEngine()->isTextMode()) {
        $view = $spec['href'];
      } else {
        $view = id(new PHUITagView())
          ->setID($tag_id)
          ->setName($this->assertFlatText($spec['href']))
          ->setHref($this->assertFlatText($spec['href']))
          ->setType(PHUITagView::TYPE_OBJECT)
          ->setExternal(true);
      }

      $engine->overwriteStoredText($spec['token'], $view);
    }

    Javelin::initBehavior('doorkeeper-tag', array('tags' => $refs));

    $engine->setTextMetadata($key, array());
  }

}
