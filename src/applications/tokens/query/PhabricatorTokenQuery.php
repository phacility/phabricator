<?php

final class PhabricatorTokenQuery
  extends PhabricatorCursorPagedPolicyAwareQuery {

  private $phids;

  public function withPHIDs(array $phids) {
    $this->phids = $phids;
    return $this;
  }

  protected function loadPage() {
    $tokens = $this->getBuiltinTokens();

    if ($this->phids) {
      $map = array_fill_keys($this->phids, true);
      foreach ($tokens as $key => $token) {
        if (empty($map[$token->getPHID()])) {
          unset($tokens[$key]);
        }
      }
    }

    return $tokens;
  }

  private function getBuiltinTokens() {
    $specs = array(
      array('like-1', pht('Like')),
      array('like-2', pht('Dislike')),
      array('heart-1', pht('Love')),
      array('heart-2', pht('Heartbreak')),
      array('medal-1', pht('Orange Medal')),
      array('medal-2', pht('Grey Medal')),
      array('medal-3', pht('Yellow Medal')),
      array('medal-4', pht('Manufacturing Defect?')),
      array('coin-1', pht('Haypence')),
      array('coin-2', pht('Piece of Eight')),
      array('coin-3', pht('Doubloon')),
      array('coin-4', pht('Mountain of Wealth')),
      array('misc-1', pht('Pterodactyl')),
      array('misc-2', pht('Evil Spooky Haunted Tree')),
      array('misc-3', pht('Baby Tequila')),
      array('misc-4', pht('The World Burns')),
    );

    $type = PhabricatorTokenTokenPHIDType::TYPECONST;

    $tokens = array();
    foreach ($specs as $id => $spec) {
      list($image, $name) = $spec;

      $token = id(new PhabricatorToken())
        ->setID($id)
        ->setName($name)
        ->setPHID('PHID-'.$type.'-'.$image);
      $tokens[] = $token;
    }

    return $tokens;
  }


  public function getQueryApplicationClass() {
    return 'PhabricatorTokensApplication';
  }

}
