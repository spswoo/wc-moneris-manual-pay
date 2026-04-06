<?php
/**
 * Plugin Name: WooCommerce Manual Moneris Payment UI (Direct API)
 * Description: Adds a custom slide-out panel for manual credit card processing. WARNING: Captures raw CC data.
 * Version: 2.3
 * Author: Your Name
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * 1 & 2. Add the Button AND the Hidden Panel safely
 */
add_action( 'woocommerce_order_item_add_action_buttons', 'wc_moneris_add_pay_ui', 10, 1 );
function wc_moneris_add_pay_ui( $order ) {
    $order = wc_get_order( $order );
            //echo "<pre>"; print_r($order); echo "</pre>";
    
    if ( ! $order ) {
        return;
    }

    // 1. Only allow payment on these specific statuses
    $allowed_statuses = array( 'pending', 'on-hold', 'failed' );
    if ( ! in_array( $order->get_status(), $allowed_statuses ) ) {
        return;
    }

    // 2. Hide if a transaction ID (Moneris receipt) is already recorded
    if ( $order->get_transaction_id() ) {
        return;
    }

    // 3. Hide if the order total is zero
    if ( $order->get_total() <= 0 ) {
        return;
    }

    echo '<button type="button" class="button manual-pay-button">Pay by Credit Card</button>';

    ?>
    <div id="manual-pay-slideout" style="display:none; padding: 15px; background: #f8f9f9; border-top: 1px solid #e2e4e7; width: 100%; box-sizing: border-box;">
        <h4 style="margin-top: 0; padding-bottom: 10px; border-bottom: 1px solid #e2e4e7;">Manual Credit Card Payment (Moneris Direct)</h4>
        
        <input type="hidden" id="manual-pay-order-id" value="<?php echo esc_attr( $order->get_id() ); ?>" />
        
        <div class="manual-pay-fields" style="display: flex; gap: 10px; margin: 15px 0;">
            <input type="text" id="moneris-cc-num" placeholder="Card Number (No Spaces)" style="flex: 2;" maxlength="20" />
            <input type="text" id="moneris-cc-exp" placeholder="MMYY" style="flex: 1;" maxlength="5" />
            <input type="text" id="moneris-cc-cvc" placeholder="CVC" style="flex: 1;" maxlength="4" />
        </div>
        
        <div id="monerisResponse" style="margin-bottom: 15px; color: #d63638; font-weight: bold;"></div>

        <div class="manual-pay-actions" style="display: flex; justify-content: space-between; align-items: center;">
            <button type="button" class="button cancel-manual-pay">Cancel</button>
            <button type="button" class="button button-primary submit-manual-pay">Process Payment</button>
        </div>
    </div>
    <?php
}

/**
 * 3. Enqueue the JavaScript (UPDATED FOR AJAX RECALCULATE SUPPORT)
 */
add_action( 'admin_footer', 'wc_moneris_admin_scripts' );
function wc_moneris_admin_scripts() {
    $screen = get_current_screen();
    if ( ! $screen || ! in_array( $screen->id, array( 'shop_order', 'woocommerce_page_wc-orders' ) ) ) {
        return;
    }
    ?>
    <script type="text/javascript">
        jQuery(document).ready(function($){
            
            // Function to securely snap the panel into the correct layout spot
            function fixLayoutPlacement() {
                if ($('#manual-pay-slideout').length && $('.wc-order-bulk-actions').length) {
                    $('#manual-pay-slideout').insertAfter('.wc-order-bulk-actions');
                }
            }

            // Run once on initial page load
            fixLayoutPlacement();

            // 1. OPEN PANEL (Using Event Delegation so it survives AJAX reloads)
            $(document).on('click', '.manual-pay-button', function(e){
                e.preventDefault();
                
                // Re-snap layout just in case WooCommerce just rebuilt the HTML
                fixLayoutPlacement(); 
                
                $('.wc-order-bulk-actions').hide();
                $('#manual-pay-slideout').slideDown();
            });

            // 2. CANCEL (Using Event Delegation)
            $(document).on('click', '.cancel-manual-pay', function(e){
                e.preventDefault();
                $('#manual-pay-slideout').slideUp(250, function() {
                    $('.wc-order-bulk-actions').show();
                });
                
                $('#monerisResponse').text(''); 
                $('#moneris-cc-num, #moneris-cc-exp, #moneris-cc-cvc').val('');
            });

            // 3. PROCESS PAYMENT (Using Event Delegation)
            $(document).on('click', '.submit-manual-pay', function(e){
                e.preventDefault();
                
                var orderId = $('#manual-pay-order-id').val();
                var ccNum   = $('#moneris-cc-num').val().replace(/\s+/g, ''); 
                var ccExp   = $('#moneris-cc-exp').val().replace(/\D/g, '');  
                var ccCvc   = $('#moneris-cc-cvc').val().replace(/\D/g, '');
                
                if(!ccNum || !ccExp) {
                    $('#monerisResponse').text('Please enter a card number and expiry.');
                    return;
                }

                $('#monerisResponse').css('color', '#333').text('Processing directly with Moneris...');
                
                $.ajax({
                    url: ajaxurl, 
                    type: 'POST',
                    data: {
                        action: 'wc_moneris_process_manual_payment',
                        order_id: orderId,
                        cc_num: ccNum,
                        cc_exp: ccExp,
                        cc_cvc: ccCvc
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#monerisResponse').css('color', 'green').text('Payment Complete! Reloading order...');
                            $('#moneris-cc-num, #moneris-cc-exp, #moneris-cc-cvc').val(''); 
                            setTimeout(function(){ location.reload(); }, 1500);
                        } else {
                            $('#monerisResponse').css('color', '#d63638').text(response.data);
                        }
                    },
                    error: function() {
                        $('#monerisResponse').css('color', '#d63638').text('Server error during processing.');
                    }
                });
            });
        });
    </script>
    <?php
}


/**
 * 4. Process the raw card data via AJAX using direct purchase in mpgClass.php
 */
add_action( 'wp_ajax_wc_moneris_process_manual_payment', 'wc_moneris_process_manual_payment' );
function wc_moneris_process_manual_payment() {
    if ( ! current_user_can( 'edit_shop_orders' ) ) {
        wp_send_json_error( 'Permission denied.' );
    }

    $order_id = isset( $_POST['order_id'] ) ? intval( $_POST['order_id'] ) : 0;
    $cc_num   = isset( $_POST['cc_num'] ) ? sanitize_text_field( $_POST['cc_num'] ) : '';
    $cc_exp   = isset( $_POST['cc_exp'] ) ? sanitize_text_field( $_POST['cc_exp'] ) : ''; // MMYY
    $cc_cvc   = isset( $_POST['cc_cvc'] ) ? sanitize_text_field( $_POST['cc_cvc'] ) : '';

    if ( ! $order_id || ! $cc_num || ! $cc_exp ) {
        wp_send_json_error( 'Missing credit card information.' );
    }

    $order = wc_get_order( $order_id );
    if ( ! $order ) {
        wp_send_json_error( 'Invalid order.' );
    }

    // FORMAT EXPIRY DATE & PREP FOR ORDER NOTE
    $formatted_exp = $cc_exp; 
    if ( strlen( $cc_exp ) === 4 ) {
        $month = substr( $cc_exp, 0, 2 );
        $year  = substr( $cc_exp, 2, 2 );
        $cc_exp = $year . $month; // YYMM format required for Moneris API
        $formatted_exp = $month . '/20' . $year; // MM/YYYY format for Order Note
    }

    // MASK CREDIT CARD FOR ORDER NOTE
    $masked_cc = 'XXXX-' . substr( $cc_num, -4 );

    // INCLUDE MPG CLASS
    require_once( plugin_dir_path( __FILE__ ) . 'mpgClasses.php' ); 

    // MONERIS CREDENTIALS 
    $store_id  = 'store5';
    $api_token = 'yesguy';
    $test_mode = true; 

    $amount = number_format( (float) $order->get_total(), 2, '.', '' );
    
    // BUILD MAIN TRANSACTION
    $txnArray = array(
        'type'       => 'purchase',
        'order_id'   => $order->get_order_number() . '-' . time(), 
        'cust_id'    => $order->get_customer_id() ? $order->get_customer_id() : 'guest_order',
        'amount'     => $amount,
        'pan'        => $cc_num,  
        'expdate'    => $cc_exp,  
        'crypt_type' => '1'       
    );
    $mpgTxn = new mpgTransaction( $txnArray );

    // ADD CVD/CVC SECURELY
    if ( ! empty( $cc_cvc ) ) {
        $cvdTemplate = array(
            'cvd_indicator' => '1',
            'cvd_value'     => $cc_cvc
        );
        $mpgCvdInfo = new mpgCvdInfo( $cvdTemplate );
        $mpgTxn->setCvdInfo( $mpgCvdInfo );
    }

    // ADD AVS INFO (Address Verification)
    // We pull the billing address directly from the WooCommerce order
    $billing_postcode = $order->get_billing_postcode();
    $billing_address  = $order->get_billing_address_1();
    
    if ( ! empty( $billing_postcode ) || ! empty( $billing_address ) ) {
        $avsTemplate = array(
            'avs_street_number' => '', // Moneris usually parses this fine if left blank and full address is passed to street_name
            'avs_street_name'   => $billing_address,
            'avs_zipcode'       => str_replace( ' ', '', $billing_postcode ) // Strip spaces from postal codes
        );
        $mpgAvsInfo = new mpgAvsInfo( $avsTemplate );
        $mpgTxn->setAvsInfo( $mpgAvsInfo );
    }

    // EXECUTE API CALL
    $mpgRequest = new mpgRequest( $mpgTxn );
    $mpgRequest->setProcCountryCode( 'CA' ); 
    $mpgRequest->setTestMode( $test_mode );

    $mpgHttpPost = new mpgHttpsPost( $store_id, $api_token, $mpgRequest );
    $mpgResponse = $mpgHttpPost->getMpgResponse();

    $response_code = $mpgResponse->getResponseCode();
    $message       = $mpgResponse->getMessage();
    $receipt_id    = $mpgResponse->getReceiptId();

    // HANDLE THE RESPONSE (Codes < 50 are Approved)
    if ( $response_code !== 'null' && (int) $response_code < 50 ) {
        
        // Map Moneris Card Type Codes to Readable Names
        $card_code = $mpgResponse->getCardType();
        $card_map  = array( 'V' => 'Visa', 'M' => 'Mastercard', 'AX' => 'American Express', 'NO' => 'Discover', 'C' => 'JCB' );
        $card_type = isset( $card_map[$card_code] ) ? $card_map[$card_code] : $card_code;

        // Fetch AVS and CVD Results
        $avs_result = $mpgResponse->getAvsResultCode() ? $mpgResponse->getAvsResultCode() : 'Not Provided';
        $cvd_result = $mpgResponse->getCvdResultCode() ? $mpgResponse->getCvdResultCode() : 'Not Provided';

        // Build the Custom Order Note
        $note  = "Moneris Manual Payment Approved\n";
        $note .= "Credit Card Type: " . $card_type . "\n";
        $note .= "Credit Card Number: " . $masked_cc . "\n";
        $note .= "Expiration Date: " . $formatted_exp . "\n";
        $note .= "CVV Response: " . $cvd_result . "\n";
        $note .= "AVS Response: " . $avs_result . "\n";
        $note .= "Transaction Id: " . $receipt_id . "\n";
        $note .= "Authorization Code: " . $mpgResponse->getAuthCode() . "\n";
        $note .= "VBV/3DS Chargeback Liability Shift: No (Manual Entry)\n";
        $note .= "Order was placed using " . $order->get_currency();

        $order->payment_complete( $receipt_id );
        $order->add_order_note( $note );
        
        wp_send_json_success( 'Payment successful!' );
    } else {
        $order->add_order_note( sprintf( 'Moneris manual Direct API payment Failed. Code: %s. Message: %s', $response_code, $message ) );
        wp_send_json_error( 'Declined: ' . $message );
    }
}