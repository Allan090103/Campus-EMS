<?php
/**
 * Application configuration.
 *
 * In a real production app the JWT secret would live in an environment
 * variable, never in source control. For this coursework demo we keep it
 * here and document that clearly.
 */

return [
    // Secret key used to SIGN and VERIFY JWT tokens.
    // Change this to any long random string for your own deployment.
    'jwt_secret' => 'campus_ems_secret_key_change_me_2026',

    // Signing algorithm.
    'jwt_alg'    => 'HS256',

    // How long a token stays valid, in seconds (8 hours).
    'jwt_ttl'    => 8 * 60 * 60,
];
