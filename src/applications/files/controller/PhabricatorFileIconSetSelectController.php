<?php

final class PhabricatorFileIconSetSelectController
  extends PhabricatorFileController {

  public function handleRequest(AphrontRequest $request) {
    $key = $request->getURIData('key');

    $set = PhabricatorIconSet::getIconSetByKey($key);
    if (!$set) {
      return new Aphront404Response();
    }

    $v_icon = $request->getStr('icon');
    if ($request->isFormPost()) {
      $icon = $set->getIcon($v_icon);

      if ($icon) {
        $payload = array(
          'value' => $icon->getKey(),
          'display' => $set->renderIconForControl($icon),
        );

        return id(new AphrontAjaxResponse())
          ->setContent($payload);
      }
    }

    require_celerity_resource('phui-icon-set-selector-css');
    Javelin::initBehavior('phabricator-tooltips');

    $ii = 0;
    $buttons = array();
    $breakpoint = ceil(sqrt(count($set->getIcons())));
    foreach ($set->getIcons() as $icon) {
      $label = $icon->getLabel();

      $view = id(new PHUIIconView())
        ->setIcon($icon->getIcon());

      $classes = array();
      $classes[] = 'icon-button';

      $is_selected = ($icon->getKey() == $v_icon);

      if ($is_selected) {
        $classes[] = 'selected';
      }

      $is_disabled = $icon->getIsDisabled();
      if ($is_disabled && !$is_selected) {
        continue;
      }

      $aural = javelin_tag(
        'span',
        array(
          'aural' => true,
        ),
        pht('Choose "%s" Icon', $label));

      $buttons[] = javelin_tag(
        'button',
        array(
          'class' => implode(' ', $classes),
          'name' => 'icon',
          'value' => $icon->getKey(),
          'type' => 'submit',
          'sigil' => 'has-tooltip',
          'meta' => array(
            'tip' => $label,
          ),
        ),
        array(
          $aural,
          $view,
        ));

      if ((++$ii % $breakpoint) == 0) {
        $buttons[] = phutil_tag('br');
      }
    }

    $buttons = phutil_tag(
      'div',
      array(
        'class' => 'icon-grid',
      ),
      $buttons);

    $dialog_title = $set->getSelectIconTitleText();

    return $this->newDialog()
      ->setTitle($dialog_title)
      ->appendChild($buttons)
      ->addCancelButton('/');
  }

}
