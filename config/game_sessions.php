<?php

return [
    // Lifetime of a signed game session token in seconds (default 4 hours).
    // Provider callbacks carrying an expired token are rejected; players relaunch the game.
    'ttl' => (int) env('GAME_SESSION_TTL', 14400),
];
