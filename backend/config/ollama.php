<?php

return [
    'base_url' => env('OLLAMA_BASE_URL', ''),
    'model' => env('OLLAMA_MODEL', ''),
    'timeout' => (int) env('OLLAMA_TIMEOUT', 45),
];
