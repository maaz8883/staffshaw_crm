<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Brief Form Builder (CRM admin UI)
    |--------------------------------------------------------------------------
    |
    | When false, builder routes return 404 and Build buttons are hidden.
    | Orbit dynamic brief forms and the resolve/submit API keep working.
    |
    */
    'builder_enabled' => env('BRIEF_FORM_BUILDER_ENABLED', false),

    /*
    | Brief Forms box (links, documents, answers) on the sale page. Hidden for now;
    | set BRIEF_SHOW_ON_SALE=true in .env to bring it back (Finance never sees it).
    */
    'show_on_sale' => env('BRIEF_SHOW_ON_SALE', false),

];
