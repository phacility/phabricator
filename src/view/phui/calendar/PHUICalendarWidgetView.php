<?php

final class PHUICalendarWidgetView extends AphrontTagView {

  private $header;
  private $list;

  public function setHeader($date) {
    $this->header = $date;
    return $this;
  }

  public function setCalendarList(PHUICalendarListView $list) {
    $this->list = $list;
    return $this;
  }

  protected function getTagName() {
    return 'div';
  }

  protected function getTagAttributes() {
    require_celerity_resource('phui-calendar-list-css');
    return array('class' => 'phui-calendar-list-container');
  }

  protected function getTagContent() {

    $header = id(new PHUIHeaderView())
      ->setHeader($this->header);

    $box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->setFlush(true)
      ->appendChild($this->list);

    return $box;
  }
}
