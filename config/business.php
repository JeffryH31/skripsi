<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Working Hours
    |--------------------------------------------------------------------------
    |
    | Business hours used for idle time calculations and capacity planning.
    |
    */
    'workday_start_hour' => 8,
    'workday_end_hour' => 17,
    'break_start_hour' => 12,
    'break_end_hour' => 13,
    'working_hours_per_day' => 8,

    /*
    |--------------------------------------------------------------------------
    | Query Limits
    |--------------------------------------------------------------------------
    |
    | Default limits for various queries to prevent unbounded result sets.
    |
    */
    'limits' => [
        'recent_activity' => 15,
        'subtask_picker' => 200,
        'board_items' => 50,
        'member_report_entries' => 30,
        'member_report_activity' => 15,
        'workspace_search' => 100,
        'calendar_activities_per_item' => 50,
    ],
];
