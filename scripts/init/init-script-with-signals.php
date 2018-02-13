<?php

// Initialize a script that will handle signals.

if (function_exists('pcntl_async_signals')) {
  pcntl_async_signals(true);
} else {
  declare(ticks = 1);
}

require_once dirname(__FILE__).'/init-script.php';
