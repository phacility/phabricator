<?php

final class PhutilXHPASTSyntaxHighlighter extends Phobject {

  public function getHighlightFuture($source) {
    $scrub = false;
    if (strpos($source, '<?') === false) {
      $source = "<?php\n".$source;
      $scrub = true;
    }

    return new PhutilXHPASTSyntaxHighlighterFuture(
      PhutilXHPASTBinary::getParserFuture($source),
      $source,
      $scrub);
  }

}
