<?php

$services = array(
  'moodle_enrol_stripepayment' => array(                      //the name of the web service
      'functions' => array ('moodle_stripepayment_couponsettings', 'moodle_stripepayment_free_enrolsettings', 'moodle_stripepayment_stripe_js_settings', 'moodle_stripepayment_success_stripe_url'), //web service functions of this service
      'requiredcapability' => '',                //if set, the web service user need this capability to access 
                                                 //any function of this service. For example: 'some/capability:specified'                 
      'restrictedusers' =>0,                      //if enabled, the Moodle administrator must link some user to this service
                                                  //into the administration
      'enabled'=> 1,                               //if enabled, the service can be reachable on a default installation
      'shortname'=>'mytesttestservice' //the short name used to refer to this service from elsewhere including when fetching a token
   )
);

$functions = array(
    'moodle_stripepayment_couponsettings' => array(
        'classname' => 'moodle_enrol_stripepayment_external',
        'methodname' => 'stripepayment_couponsettings',
        'classpath' => 'enrol/stripepayment/externallib.php',
        'description' => 'Load coupon settings data',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ),
    'moodle_stripepayment_free_enrolsettings' => array(
        'classname' => 'moodle_enrol_stripepayment_external',
        'methodname' => 'stripepayment_free_enrolsettings',
        'classpath' => 'enrol/stripepayment/externallib.php',
        'description' => 'Update settings data for free enrol',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ),
    'moodle_stripepayment_stripe_js_settings' => array(
        'classname' => 'moodle_enrol_stripepayment_external',
        'methodname' => 'stripe_js_method',
        'classpath' => 'enrol/stripepayment/externallib.php',
        'description' => 'Update settings data for stripe connect',
        'type' => 'write',
        'ajax' => true,
        'loginrequired' => true,
    ),
    'moodle_stripepayment_success_stripe_url' => array(
        'classname' => 'moodle_enrol_stripepayment_external',
        'methodname' => 'success_stripe_url',
        'classpath' => 'enrol/stripepayment/externallib.php',
        'description' => 'Update settings data stripe description',
        'type' => 'write',
        'ajax' => true,
    )
);