<?php

return [
    // Secret token guarding the /__deploy migrate endpoint. Set DEPLOY_TOKEN in
    // the production .env (and as a GitHub Actions secret). If empty, the
    // endpoint is disabled (returns 403).
    'token' => env('DEPLOY_TOKEN'),
];
