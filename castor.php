<?php

declare(strict_types=1);

use function Castor\import;

defined('CASTOR_USE_CHDIR') || define('CASTOR_USE_CHDIR', true);

// Import all commands from tools/castor/
import(__DIR__ . '/tools/castor/Commands');
import(__DIR__ . '/tools/castor/Builder');
import(__DIR__ . '/tools/castor/Enum');
import(__DIR__ . '/tools/castor/Support');
