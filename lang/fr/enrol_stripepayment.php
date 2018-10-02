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
 * Strings for component 'enrol_stripepayment', language 'fr'.
 *
 * @package    enrol_stripepayment
 * @copyright  2018 Dualcube, Arkaprava Midya, Parthajeet Chakraborty, Louis Bronne
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
$string['addtogroup'] = "Ajouter au groupe";
$string['addtogroup_help'] = "Si vous sélectionnez un groupe ici, alors l'utilisateur y sera ajouté à la fin du processus de paiement quand il est inscrit à ce cours.";
$string['assignrole'] = "Attribuer un rôle";
$string['assignrole_help'] = "Si vous sélectionnez un rôle ici, alors il sera attribué à l'utilisateur à la fin du processus de paiement quand il est inscrit à ce cours.";
$string['billingaddress'] = "Une adresse de facturation doit être communiquée.";
$string['billingaddress_desc'] = 'This sets the Stripe payment option for whether the user should be asked to input their billing address. It is off by default, but it is a good idea to turn it on.';
$string['secretkey'] = "Clé privée Stripe";
$string['publishablekey'] = "Clé publique Stripe";
$string['secretkey_desc'] = "Clé API privée du compte Stripe";
$string['publishablekey_desc'] = "Clé API publique du compte Stripe";
$string['cost'] = "Coût d'inscription";
$string['costerror'] = "Le coût d'inscription n'est pas numérique.";
$string['costorkey'] = "Merci de choisir une des méthodes d'inscription suivantes.";
$string['currency'] = "Devise";
$string['customwelcomemessage'] = "Message de bienvenue";
$string['customwelcomemessage_help'] = 'If you enter some text here, it will be shown instead of the standard text "This course requires a payment for entry." on the Enrollment options page that students see when they attempt to access a course they are not enrolled in. If you leave this blank, the standard text will be used.';
$string['defaultrole'] = "Rôle attribué par défaut";
$string['defaultrole_desc'] = "Choisissez un rôle qui doit être attribué aux utilisateurs pendant les inscriptions via Stripe.";
$string['enrolenddate'] = 'Date de fin';
$string['enrolenddate_help'] = "Si coché, les utilisateurs peuvent s'inscrire uniquement jusqu'à cette date.";
$string['enrolenddaterror'] = "La date de fin d'inscription ne peut être antérieure à la date de début.";
$string['enrolperiod'] = 'Durée de l'inscription';
$string['enrolperiod_desc'] = 'Default length of time that the enrolment is valid. If set to zero, the enrolment duration will be unlimited by default.';
$string['enrolperiod_help'] = 'Length of time that the enrolment is valid, starting with the moment the user is enrolled. If disabled, the enrolment duration will be unlimited.';
$string['enrolstartdate'] = 'Date de début';
$string['enrolstartdate_help'] = 'If enabled, users can be enrolled from this date onward only.';
$string['expiredaction'] = 'Enrolment expiration action';
$string['expiredaction_help'] = 'Select action to carry out when user enrolment expires. Please note that some user data and settings are purged from course during course unenrolment.';
$string['mailadmins'] = 'Prévenir l'administrateur';
$string['mailstudents'] = 'Prévenir les étudiants';
$string['mailteachers'] = 'Prévenir les professeurs';
$string['messageprovider:stripe_enrolment'] = "Message d'inscription Stripe";
$string['nocost'] = "Il n'y a pas de coût associé à l'inscription à ce cours!";
$string['stripe:config'] = 'Configure Stripe enrol instances';
$string['stripe:manage'] = 'Manage enrolled users';
$string['stripe:unenrol'] = 'Unenrol users from course';
$string['stripe:unenrolself'] = 'Unenrol self from the course';
$string['stripeaccepted'] = 'Paiements Stripe acceptés';
$string['pluginname'] = 'Paiement Stripe';
$string['pluginname_desc'] = 'The Stripe module allows you to set up paid courses.  If the cost for any course is zero, then students are not asked to pay for entry.  There is a site-wide cost that you set here as a default for the whole site and then a course setting that you can set for each course individually. The course cost  overrides the site cost.';
$string['sendpaymentbutton'] = 'Envoyer les paiements via Stripe';
$string['status'] = 'Autoriser les inscriptions via Stripe';
$string['status_desc'] = 'Allow users to use Stripe to enrol into a course by default.';
$string['unenrolselfconfirm'] = 'Voulez-vous vraiment vous désinscrire du cours "{$a}" ?';
$string['messageprovider:stripepayment_enrolment'] = 'Message Provider';
$string['validatezipcode'] = 'Merci de vérifier le code postal de facturation';
$string['validatezipcode_desc'] = 'This sets the Stripe payment option for whether the billing address should be verified as part of processing the payment. They stronngly recommend that this option should be on, to reduce fraud.';
$string['maxenrolled'] = 'Max enrolled users';
$string['maxenrolled_help'] = 'Specifies the maximum number of users that can stripepayment enrol. 0 means no limit.';
$string['maxenrolledreached'] = 'Maximum number of users allowed to stripepayment-enrol was already reached.';
$string['canntenrol'] = 'Enrolment is disabled or inactive';
$string['stripepayment:config'] = 'Configure stripepayment';
$string['stripepayment:manage'] = 'Manage stripepayment';
$string['stripepayment:unenrol'] = 'Unenrol stripepayment';
$string['stripepayment:unenrolself'] = 'Unenrolself stripepayment';
$string['charge_description1'] = "create customer for email receipt";
$string['charge_description2'] = 'Inscription aux cours.';
$string['stripe_sorry'] = "Sorry, you can not use the script that way.";
