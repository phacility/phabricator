<?php

final class NuanceFormItemType
  extends NuanceItemType {

  const ITEMTYPE = 'form.item';

  public function getItemTypeDisplayName() {
    return pht('Form');
  }

  public function getItemDisplayName(NuanceItem $item) {
    return pht('Complaint');
  }

  protected function newWorkCommands(NuanceItem $item) {
    return array(
      $this->newCommand('trash')
        ->setIcon('fa-trash')
        ->setName(pht('Throw In Trash')),
    );
  }

  protected function newItemView(NuanceItem $item) {
    $viewer = $this->getViewer();

    $content = $item->getItemProperty('complaint');
    $content_view = id(new PHUIRemarkupView($viewer, $content))
      ->setContextObject($item);

    $content_section = id(new PHUIPropertyListView())
      ->addTextContent(
        phutil_tag(
          'div',
          array(
            'class' => 'phabricator-remarkup',
          ),
          $content_view));

    $content_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Complaint'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->appendChild($content_section);

    return array(
      $content_box,
    );
  }

  protected function handleAction(NuanceItem $item, $action) {
    return null;
  }

}
