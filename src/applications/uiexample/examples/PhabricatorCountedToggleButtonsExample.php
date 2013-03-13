<?php

final class PhabricatorCountedToggleButtonsExample
  extends PhabricatorUIExample {

  public function getName() {
    return 'Counted Toggle Buttons';
  }

  public function getDescription() {
    return 'Like AphrontFormToggleButtonsControl, but with counters.';
  }

  private static function buildTreesList() {
    $all_trees = array(
      array(
        'name'      => 'Oak',
        'leaves'    => 'deciduous',
        'wood'      => 'hard',
        'branches'  => 'climbable',
      ),
      array(
        'name'      => 'Pine',
        'leaves'    => 'coniferous',
        'wood'      => 'soft',
        'branches'  => 'spindly',
      ),
      array(
        'name'      => 'Spruce',
        'leaves'    => 'coniferous',
        'wood'      => 'soft',
        'branches'  => 'sticky',
      ),
      array(
        'name'      => 'Ash',
        'leaves'    => 'deciduous',
        'wood'      => 'hard',
        'branches'  => 'climbable',
      ),
      array(
        'name'      => 'Holly',
        'leaves'    => 'waxy',
        'wood'      => 'hard',
        'branches'  => 'prickly',
      ),
    );

    for ($ii = 0; $ii < 345; $ii++) {
      $name = sprintf("Soylent UltraTree \xE2\x84\xA2 Mutation 0xPD%03x", $ii);
      $all_trees[] = array(
        'name'      => $name,
        'leaves'    => 'carcinogenic',
        'wood'      => 'metallic',
        'branches'  => 'sentient',
      );
    }

    return $all_trees;
  }

  public function renderExample() {
    $request = $this->getRequest();

    $form = id(new AphrontFormView())
      ->setUser($request->getUser());

    $attributes = array('leaves', 'wood', 'branches');

    $all_trees = self::buildTreesList();
    $trees = $all_trees;

    foreach ($attributes as $attribute) {
      $form_value = $request->getStr($attribute);

      $buttons = array(null => 'all');
      foreach ($all_trees as $dict) {
        $value = $dict[$attribute];
        $buttons[$value] = $value;
      }

      // The trees filtered by other attributes, before we filter by this
      // attribute
      $trees_before = $all_trees;
      foreach ($attributes as $other_attribute) {
        if ($other_attribute != $attribute) {
          $trees_before = $this->filterTrees($trees_before, $other_attribute);
        }
      }

      $counters = array(null => count($trees_before));
      foreach ($trees_before as $dict) {
        $value = $dict[$attribute];
        if (!isset($counters[$value])) {
          $counters[$value] = 0;
        }
        $counters[$value]++;
      }

      $trees = $this->filterTrees($trees, $attribute);

      $control = id(new AphrontFormCountedToggleButtonsControl())
        ->setLabel(ucfirst($attribute))
        ->setName($attribute)
        ->setValue($form_value)
        ->setBaseURI($request->getRequestURI(), $attribute)
        ->setButtons($buttons)
        ->setCounters($counters);

      $form->appendChild($control);
    }

    $rows = array();
    foreach ($trees as $dict) {
      $row = array_select_keys($dict, $attributes);
      array_unshift($row, $dict['name']);
      $rows[] = $row;
    }

    $headers = $attributes;
    array_unshift($headers, 'name');
    $table = id(new AphrontTableView($rows))
      ->setHeaders($headers);

    $panel = id(new AphrontPanelView())
      ->setHeader('Counters!')
      ->setWidth(AphrontPanelView::WIDTH_FULL)
      ->appendChild($form)
      ->appendChild($table);

    return $panel;
  }

  private function filterTrees($trees, $attribute) {
    $form_value = $this->getRequest()->getStr($attribute);
    if (!$form_value) {
      return $trees;
    }

    $new = array();
    foreach ($trees as $dict) {
      if ($dict[$attribute] == $form_value) {
        $new[] = $dict;
      }
    }
    return $new;
  }

}
