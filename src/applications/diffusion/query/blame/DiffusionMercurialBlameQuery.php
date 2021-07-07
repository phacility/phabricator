<?php

final class DiffusionMercurialBlameQuery extends DiffusionBlameQuery {

  protected function newBlameFuture(DiffusionRequest $request, $path) {
    $repository = $request->getRepository();
    $commit = $request->getCommit();

    // NOTE: Using "--template" or "--debug" to get the full commit hashes.
    $hg_analyzer = PhutilBinaryAnalyzer::getForBinary('hg');
    if ($hg_analyzer->isMercurialAnnotateTemplatesAvailable()) {
      // See `hg help annotate --verbose` for more info on the template format.
      // Use array of arguments so the template line does not need wrapped in
      // quotes.
      $template = array(
        '--template',
        "{lines % '{node}: {line}'}",
      );
    } else {
      $template = array(
        '--debug',
        '--changeset',
      );
    }

    return $repository->getLocalCommandFuture(
      'annotate %Ls --rev %s -- %s',
      $template,
      $commit,
      $path);
  }

  protected function resolveBlameFuture(ExecFuture $future) {
    list($err, $stdout) = $future->resolve();

    if ($err) {
      return null;
    }

    $result = array();

    $lines = phutil_split_lines($stdout);
    foreach ($lines as $line) {
      // If the `--debug` flag was used above instead of `--template` then
      // there's a good change additional output was included which is not
      // relevant to the information we want. It should be safe to call this
      // regardless of whether we used `--debug` or `--template` so there isn't
      // a need to track which argument was used.
      $line = DiffusionMercurialCommandEngine::filterMercurialDebugOutput(
        $line);

      // Just in case new versions of Mercurial add arbitrary output when using
      // the `--debug`, do a quick sanity check that this line is formatted in
      // a way we're expecting.
      if (strpos($line, ':') === false) {
        phlog(pht('Unexpected output from hg annotate: %s', $line));
        continue;
      }
      list($commit) = explode(':', $line, 2);
      $result[] = $commit;
    }

    return $result;
  }

}
