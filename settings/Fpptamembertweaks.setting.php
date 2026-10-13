<?php

use CRM_Fpptamembertweaks_ExtensionUtil as E;

return [
  'fpptamembertweaks_renewal_open_date' => [
    'name' => 'fpptamembertweaks_renewal_open_date',
    'type' => 'String',
    'title' => E::ts('Membership renewal open date'),
    'description' => E::ts('Month-day (e.g. "01/13" for January 13) of the date each year on which membership renewals should open. Must be in the format MM/DD. "02/29" is not allowed.'),
    'default' => '10/15',
    'html_type' => 'Text',
    'is_domain' => 1,
    'is_contact' => 0,
    'settings_pages' => ['fpptamembertweaks' => ['weight' => 5]],
    'validate_callback' => 'CRM_Fpptamembertweaks_Utils_Settings::validateRenewalOpenDate',
  ],
];
