<?php

// Compatibility entry point. Generate production and beta assets from one source.
passthru('bun '.escapeshellarg(__DIR__.'/generate.mjs'), $status);
exit($status);
