<?php

abstract class DoorkeeperRemarkupRule extends PhutilRemarkupRule {

  const KEY_TAGS = 'doorkeeper.tags';

  const VIEW_FULL = 'full';
  const VIEW_SHORT = 'short';

  public function getPriority() {
    return 350.0;
  }

  protected function addDoorkeeperTag(array $spec) {
    PhutilTypeSpec::checkMap(
      $spec,
      array(
        'href' => 'string',
        'tag' => 'map<string, wild>',

        'name' => 'optional string',
        'view' => 'optional string',
      ));

    $spec = $spec + array(
      'view' => self::VIEW_FULL,
    );

    $views = array(
      self::VIEW_FULL,
      self::VIEW_SHORT,
    );
    $views = array_fuse($views);
    if (!isset($views[$spec['view']])) {
      throw new Exception(
        pht(
          'Unsupported Doorkeeper tag view mode "%s". Supported modes are: %s.',
          $spec['view'],
          implode(', ', $views)));
    }

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
      $href = $spec['href'];
      $name = idx($spec, 'name', $href);

      $this->assertFlatText($href);
      $this->assertFlatText($name);

      if ($this->getEngine()->isTextMode()) {
        $view = "{$name} <{$href}>";
      } else {
        $tag_id = celerity_generate_unique_node_id();

        $refs[] = array(
          'id' => $tag_id,
          'view' => $spec['view'],
        ) + $spec['tag'];

        $view = id(new PHUITagView())
          ->setID($tag_id)
          ->setName($name)
          ->setHref($href)
          ->setType(PHUITagView::TYPE_OBJECT)
          ->setExternal(true);
      }

      $engine->overwriteStoredText($spec['token'], $view);
    }

    if ($refs) {
      Javelin::initBehavior('doorkeeper-tag', array('tags' => $refs));
    }

    $engine->setTextMetadata($key, array());
  }

}
