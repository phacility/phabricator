<?php

final class HarbormasterUnitSummaryView extends AphrontView {

  private $buildable;
  private $messages;
  private $limit;
  private $showViewAll;

  public function setBuildable(HarbormasterBuildable $buildable) {
    $this->buildable = $buildable;
    return $this;
  }

  public function setUnitMessages(array $messages) {
    $this->messages = $messages;
    return $this;
  }

  public function setLimit($limit) {
    $this->limit = $limit;
    return $this;
  }

  public function setShowViewAll($show_view_all) {
    $this->showViewAll = $show_view_all;
    return $this;
  }

  public function render() {
    $messages = $this->messages;
    $buildable = $this->buildable;

    $id = $buildable->getID();
    $full_uri = "/harbormaster/unit/{$id}/";

    $messages = msort($messages, 'getSortKey');
    $head_unit = head($messages);
    if ($head_unit) {
      $status = $head_unit->getResult();

      $tag_text = HarbormasterUnitStatus::getUnitStatusLabel($status);
      $tag_color = HarbormasterUnitStatus::getUnitStatusColor($status);
      $tag_icon = HarbormasterUnitStatus::getUnitStatusIcon($status);
    } else {
      $tag_text = pht('No Unit Tests');
      $tag_color = 'grey';
      $tag_icon = 'fa-ban';
    }

    $tag = id(new PHUITagView())
      ->setType(PHUITagView::TYPE_SHADE)
      ->setColor($tag_color)
      ->setIcon($tag_icon)
      ->setName($tag_text);

    $header = id(new PHUIHeaderView())
      ->setHeader(array(pht('Unit Tests'), $tag));

    if ($this->showViewAll) {
      $view_all = id(new PHUIButtonView())
        ->setTag('a')
        ->setHref($full_uri)
        ->setIcon('fa-list-ul')
        ->setText('View All');
      $header->addActionLink($view_all);
    }

    $box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY);

    $table = id(new HarbormasterUnitPropertyView())
      ->setViewer($this->getViewer())
      ->setUnitMessages($messages);

    if ($this->showViewAll) {
      $table->setFullResultsURI($full_uri);
    }

    if ($this->limit) {
      $table->setLimit($this->limit);
    }

    $box->setTable($table);

    return $box;
  }

}
