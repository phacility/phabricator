<?php

final class DivinerDefaultRenderer extends DivinerRenderer {

  public function renderAtom(DivinerAtom $atom) {
    return "ATOM: ".$atom->getType()." ".$atom->getName()."!";
  }

  public function renderAtomSummary(DivinerAtom $atom) {
    return "A lovely atom named ".$atom->getName();
  }

  public function renderAtomIndex(array $refs) {
    $refs = msort($refs, 'getSortKey');

    $groups = mgroup($refs, 'getGroup');

    $out = array();
    foreach ($groups as $group_key => $refs) {
      $out[] = '<h1>'.$group_key.'</h1>';
      $out[] = '<ul>';
      foreach ($refs as $ref) {
        $out[] = '<li>'.$ref->getName().' - '.$ref->getSummary().'</li>';
      }
      $out[] = '</ul>';
    }
    $out = implode("\n", $out);

    return $out;
  }


}
