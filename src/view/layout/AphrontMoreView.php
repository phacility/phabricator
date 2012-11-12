<?php

final class AphrontMoreView extends AphrontView {

  private $some;
  private $more;
  private $expandtext;

  public function setSome($some) {
    $this->some = $some;
    return $this;
  }

  public function setMore($more) {
    $this->more = $more;
    return $this;
  }

  public function setExpandText($text) {
    $this->expandtext = $text;
    return $this;
  }

  public function render() {
    $some = $this->some;

    $text = "(Show More\xE2\x80\xA6)";
    if ($this->expandtext !== null) {
      $text = $this->expandtext;
    }

    $link = null;
    if ($this->more && $this->more != $this->some) {
      Javelin::initBehavior('aphront-more');
      $link = ' '.javelin_render_tag(
        'a',
        array(
          'sigil'       => 'aphront-more-view-show-more',
          'mustcapture' => true,
          'href'        => '#',
          'meta'        => array(
            'more' => $this->more,
          ),
        ),
        $text);
    }

    return javelin_render_tag(
      'div',
      array(
        'sigil' => 'aphront-more-view',
      ),
      $some.$link);
  }
}
