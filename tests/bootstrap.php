<?php

require __DIR__ . '/../vendor/autoload.php';

// a PCRE error (pattern too large, backtrack limit, ...) fails the test instead of reading as "no match"
\GunsNPrices\Internal\Rx::$strict = true;
