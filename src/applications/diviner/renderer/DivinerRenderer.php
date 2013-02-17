<?php

abstract class DivinerRenderer {

  abstract public function renderAtom(DivinerAtom $atom);
  abstract public function renderAtomSummary(DivinerAtom $atom);
  abstract public function renderAtomIndex(array $refs);

}
