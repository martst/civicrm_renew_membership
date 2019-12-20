<?php
/**
  *
  * Written by M.Stubbs
  *
  * Use at your risk. Don't run this software unless you understand what it is doing
  *
  */
require_once '/srv/www/vhosts/www.wbct.org.uk/administrator/components/com_civicrm/civicrm.settings.php';
require_once 'CRM/Core/Config.php';
$config = CRM_Core_Config::singleton( );
require_once 'api/api.php';

    $datenow = new DateTime('NOW');
    $dategrace = $datenow->sub(new DateInterval("P3M"));
    
    // find how many membership records there are
    $params = array(
        'version' => 3,
    );    
   $count = civicrm_api('Membership', 'getcount', $params);
   
   echo "Memberships found " . $count . "\n";
   
   // Get all the membership records
   $memberships = civicrm_api3('Membership', 'get', [
        'sequential' => 1,
        'options' => array(
        'limit' => $count,
        ),
    ]);
 
    $howmanytodone = 0;
 
    foreach ($memberships['values'] as $membership) {
        //print_r($membership);
        $contactID = $membership['contact_id'];
        $membershipID = $membership['id'];
        // Date is incremented for monthly or yearly but not other types
        if (strpos($membership['membership_name'], 'Monthly') !== false) {
            $interval = "P1M";
        } elseif (strpos($membership['membership_name'], 'Yearly') !== false) {
            $interval = "P1Y";
        } else {
            // life, corporate or 200 club
            $interval = NULL;
        }     
        If ( isset($interval) ) {
            // Fetch all the contributions for this contact. I assume they are all membership
            // I could/should check they are "member dues"
            $contrib_result = civicrm_api3('Contribution', 'get', [
                'sequential' => 1,
                'contact_id' => $contactID,
            ]);
 
            $lastrdate = DateTime::createFromFormat('Y-m-d', $membership['start_date']);
            foreach( $contrib_result['values'] as $contribution) {
                echo "Contribution " . $contribution['receive_date'] . "\n";
                $rdate = DateTime::createFromFormat('Y-m-d H:i:s', $contribution['receive_date']);
                if ( $rdate > $lastrdate ) {
                    $lastrdate = $rdate;
                }
            }
            // Extent the last contribution date by the subscription period
            $lastrdate->add(new DateInterval($interval));
                        
            If ( $lastrdate < $datenow ) {
                if ($lastrdate < $dategrace ) {
                    $status = "Expired";
                } else {
                    $status = "Grace";
                }
            } else {
                $status = "Current";
            }
            // Update the membership record with new end date and status
            $result = civicrm_api3('Membership', 'create', [
                'id' => $membershipID,
                'contact_id' => $contactID,
                'end_date' => $lastrdate->format('Y-m-d'),
                'status_id' => $status,
            ]);
            
            echo "ContactId " . $contactID . " new last date " . $lastrdate->format( DateTime::ISO8601 ) . " status " . $status . "\n";
            
        } elseif (strpos($membership['membership_name'], 'Life') !== false) {
            // Update the status for life members 
            $result = civicrm_api3('Membership', 'create', [
                'id' => $membershipID,
                'contact_id' => $contactID,
                'status_id' => "Current",
            ]);
            echo "ContactId " . $contactID . " life member " . " status current" . "\n";
        }
//        print_r($result);
        // Limit the number of records processed to allow testing
        $howmanytodone++;
        If ( $howmanytodone > 2500 ) {
          exit();
        }  
        
    }
