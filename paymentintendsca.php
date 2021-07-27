<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Listens for Instant Payment Notification from Stripe
 *
 * This script waits for Payment notification from Stripe,
 * then double checks that data by sending it back to Stripe.
 * If Stripe verifies this then it sets up the enrolment for that
 * user.
 *
 * @package    enrol_stripepayment
 * @copyright  2019 Dualcube Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_login();
require('Stripe/init.php');
global $DB, $USER, $CFG, $_SESSION;
session_start();
$plugin = enrol_get_plugin('stripepayment');
$secretkey = $plugin->get_config('secretkey');
$courseid = $_SESSION['courseid'];
$amount = $_SESSION['amount'];
$currency = $_SESSION['currency'];
$description = $_SESSION['description'];

if (empty($secretkey) || empty($courseid) || empty($amount) || empty($currency) || empty($description)) {
    redirect($CFG->wwwroot.'/course/view.php?id='.$courseid);
} else {
    // Set API key 
    \Stripe\Stripe::setApiKey($secretkey); 
     
    $response = array( 
        'status' => 0, 
        'error' => array( 
            'message' => 'Invalid Request!'    
        ) 
    );
     
    if ($_SERVER['REQUEST_METHOD'] == 'POST') { 
        $input = file_get_contents('php://input'); 
        $request = json_decode($input);     
    } 

    if (json_last_error() !== JSON_ERROR_NONE) { 
        http_response_code(400); 
        echo json_encode($response); 
        exit; 
    }

    if(!empty($request->checkoutSession)) { 
        // Create new Checkout Session for the order 
        try {
            $session = \Stripe\Checkout\Session::create([ 
                'payment_method_types' => ['card'], 
                'line_items' => [[ 
                    'price_data' => [ 
                        'product_data' => [ 
                            'name' => $description, 
                            'metadata' => [ 
                                'pro_id' => $courseid 
                            ]
                        ],
                        'unit_amount' => $amount, 
                        'currency' => $currency, 
                    ],
                    'quantity' => 1, 
                    'description' => $description, 
                ]], 
                'mode' => 'payment',
                'success_url' => $CFG->wwwroot.'/enrol/stripepayment/charge.php?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $CFG->wwwroot.'/course/view.php?id='.$courseid, 
            ]);
        }catch(Exception $e) {
            $api_error = $e->getMessage();  
        } 
         
        if(empty($api_error) && $session) { 
            $response = array( 
                'status' => 1, 
                'message' => 'Checkout Session created successfully!', 
                'sessionId' => $session['id'] 
            ); 
        } else { 
            $response = array( 
                'status' => 0,
                'error' => array( 
                    'message' => 'Checkout Session creation failed! '.$api_error    
                ) 
            ); 
        }
    }
    // Return response 
    echo json_encode($response);
}