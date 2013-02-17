<?php

final class DivinerDefaultRenderer extends DivinerRenderer {

  public function renderAtom(DivinerAtom $atom) {
    return "ATOM: ".$atom->getType()." ".$atom->getName()."!";
  }

}
