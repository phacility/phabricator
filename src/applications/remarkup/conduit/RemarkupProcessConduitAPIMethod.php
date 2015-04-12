<?php

final class RemarkupProcessConduitAPIMethod extends ConduitAPIMethod {

  public function getAPIMethodName() {
    return 'remarkup.process';
  }

  public function getMethodStatus() {
    return self::METHOD_STATUS_UNSTABLE;
  }

  public function getMethodDescription() {
    return 'Process text through remarkup in phabricator context.';
  }

  protected function defineReturnType() {
    return 'nonempty dict';
  }

  protected function defineErrorTypes() {
    return array(
      'ERR-NO-CONTENT' => 'Content may not be empty.',
      'ERR-INVALID-ENGINE' => 'Invalid markup engine.',
    );
  }

  protected function defineParamTypes() {
    $available_contexts = array_keys($this->getEngineContexts());
    $available_const = $this->formatStringConstants($available_contexts);

    return array(
      'context' => 'required '.$available_const,
      'contents' => 'required list<string>',
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $contents = $request->getValue('contents');
    $context = $request->getValue('context');

    $engine_class = idx($this->getEngineContexts(), $context);
    if (!$engine_class) {
      throw new ConduitException('ERR-INVALID_ENGINE');
    }

    $engine = PhabricatorMarkupEngine::$engine_class();
    $engine->setConfig('viewer', $request->getUser());

    $results = array();
    foreach ($contents as $content) {
      $text = $engine->markupText($content);
      if ($text) {
        $content = hsprintf('%s', $text)->getHTMLContent();
      } else {
        $content = '';
      }
      $results[] = array(
        'content' => $content,
      );
    }
    return $results;
  }

  private function getEngineContexts() {
    return array(
      'phriction' => 'newPhrictionMarkupEngine',
      'maniphest' => 'newManiphestMarkupEngine',
      'differential' => 'newDifferentialMarkupEngine',
      'phame' => 'newPhameMarkupEngine',
      'feed' => 'newFeedMarkupEngine',
      'diffusion' => 'newDiffusionMarkupEngine',
    );
  }

}
