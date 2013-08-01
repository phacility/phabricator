<?php

final class DoorkeeperRemarkupRuleAsana
  extends PhutilRemarkupRule {

  const KEY_TAGS = 'doorkeeper.tags';

  public function getPriority() {
    return 350.0;
  }

  public function apply($text) {
    return preg_replace_callback(
      '@https://app\\.asana\\.com/0/(\\d+)/(\\d+)@',
      array($this, 'markupAsanaLink'),
      $text);
  }

  public function markupAsanaLink($matches) {
    $key = self::KEY_TAGS;
    $engine = $this->getEngine();
    $token = $engine->storeText('AsanaDoorkeeper');

    $tags = $engine->getTextMetadata($key, array());

    $tags[] = array(
      'token' => $token,
      'href' => $matches[0],
      'tag' => array(
        'ref' => array(
          DoorkeeperBridgeAsana::APPTYPE_ASANA,
          DoorkeeperBridgeAsana::APPDOMAIN_ASANA,
          DoorkeeperBridgeAsana::OBJTYPE_TASK,
          $matches[2],
        ),
        'extra' => array(
          'asana.context' => $matches[1],
        ),
      ),
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

      $view = id(new PhabricatorTagView())
        ->setID($tag_id)
        ->setName($spec['href'])
        ->setHref($spec['href'])
        ->setType(PhabricatorTagView::TYPE_OBJECT)
        ->setExternal(true);

      $engine->overwriteStoredText($spec['token'], $view);
    }

    Javelin::initBehavior('doorkeeper-tag', array('tags' => $refs));

    $engine->setTextMetadata($key, array());
  }

}
