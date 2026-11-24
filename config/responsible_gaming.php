<?php

return [
    // Hours before a raised or removed deposit limit takes effect (decreases are immediate).
    'increase_delay_hours' => (int) env('RG_LIMIT_INCREASE_DELAY_HOURS', 24),
];
