<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'ably' => [
        'key' => env('ABLY_KEY'),
    ],

    'autonap' => [
        /*
         * Write a JSON snapshot of every incoming /api/jobs request to
         * storage/app/autonap_audit/YYYY-MM-DD/{job_id}.json for traceback.
         * Temporary — set to false once data quality is stable.
         */
        'audit_enabled' => env('AUTONAP_AUDIT_ENABLED', true),

        /*
         * When true, store the full 13-digit PID in audit files.
         * When false (default), mask to xxxxxxxxx + last 4 digits.
         */
        'audit_full_pid' => env('AUTONAP_AUDIT_FULL_PID', false),

        /*
         * Number of queue workers running in Supervisor (numprocs). Used to
         * decide whether a newly dispatched job is "queued" (all workers busy)
         * or can start immediately. Keep in sync with
         * /etc/supervisor/conf.d/autonap-worker.conf.
         */
        'worker_count' => (int) env('AUTONAP_WORKER_COUNT', 4),
    ],

];
