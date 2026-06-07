<?php

/*
 |--------------------------------------------------------------------------
 | Branch / multi-branch configuration
 |--------------------------------------------------------------------------
 |
 | main_id            The branch id that acts as the "super workspace".
 |                    When the active branch == main_id, BranchContextService
 |                    ::scopeQuery() skips filtering and the dashboard /
 |                    reports / lists return cross-branch aggregates.
 |                    Override per-deploy via .env's BRANCH_MAIN_ID.
 */
return [
    'main_id' => env('BRANCH_MAIN_ID', 1),
];
