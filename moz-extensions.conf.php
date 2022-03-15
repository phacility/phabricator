<?php

return array(
  'load-libraries' => array(
    'moz-extensions' => '/app/moz-extensions/src/',
  ),
  'events.listeners' => array(
    'LandoLinkEventListener',
    'NewChangesLinkEventListener',
    'RiskAnalyzerEventListener',
  )
);
