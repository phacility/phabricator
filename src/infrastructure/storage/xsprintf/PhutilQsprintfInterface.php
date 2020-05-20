<?php

interface PhutilQsprintfInterface {
  public function escapeBinaryString($string);
  public function escapeUTF8String($string);
  public function escapeColumnName($string);
  public function escapeMultilineComment($string);
  public function escapeStringForLikeClause($string);
}
