<?php

final class DoorkeeperAsanaRemarkupRule
  extends DoorkeeperRemarkupRule {

  public function apply($text) {
    return preg_replace_callback(
      '@https://app\\.asana\\.com/0/(\\d+)/(\\d+)@',
      array($this, 'markupAsanaLink'),
      $text);
  }

  public function markupAsanaLink($matches) {
    return $this->addDoorkeeperTag(
      array(
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
      ));
  }

}
