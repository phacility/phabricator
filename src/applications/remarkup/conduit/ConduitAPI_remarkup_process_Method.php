<?php

final class ConduitAPI_remarkup_process_Method extends ConduitAPIMethod {

  public function getMethodStatus() {
    return self::METHOD_STATUS_UNSTABLE;
  }

  public function getMethodDescription() {
    return 'Process text through remarkup in phabricator context.';
  }

  public function defineReturnType() {
    return 'nonempty dict';
  }

  public function defineErrorTypes() {
    return array(
      'ERR-NO-CONTENT' => 'Content may not be empty.',
      'ERR-INVALID-ENGINE' => 'Invalid markup engine.',
    );
  }

  public function defineParamTypes() {
    $available_contexts = array_keys($this->getEngineContexts());
    $available_contexts = implode(', ', $available_contexts);

    return array(
      'context' => 'required enum<'.$available_contexts.'>',
      'content' => 'required string',
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $content = $request->getValue('content');
    $context = $request->getValue('context');

    $engine_class = idx($this->getEngineContexts(), $context);
    if (!$engine_class) {
      throw new ConduitException('ERR-INVALID_ENGINE');
    }

    $engine = PhabricatorMarkupEngine::$engine_class();
    $engine->setConfig('viewer', $request->getUser());

    $text = $engine->markupText($content);
    if ($text) {
      $content = hsprintf('%s', $text)->getHTMLContent();
    } else {
      $content = '';
    }

    $result = array(
      'content' => $content,
    );

    return $result;
  }

  private function getEngineContexts() {
    return array(
      'phriction' => 'newPhrictionMarkupEngine',
      'maniphest' => 'newManiphestMarkupEngine',
      'differential' => 'newDifferentialMarkupEngine',
    );
  }
}
