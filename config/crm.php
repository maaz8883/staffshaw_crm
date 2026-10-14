<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Single company mode
    |--------------------------------------------------------------------------
    |
    | When true, company CRUD, filters, and dropdowns stay hidden. Records
    | still keep company_id. New users and teams are stored on the default
    | company. Set CRM_SINGLE_COMPANY=false to bring the multi-company UI back
    | without changing existing rows.
    |
    */

    'single_company' => filter_var(env('CRM_SINGLE_COMPANY', true), FILTER_VALIDATE_BOOLEAN),

    'default_company_name' => env('CRM_DEFAULT_COMPANY', 'Staffshaw'),

];
