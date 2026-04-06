<?php
/**
 * Plugin Name: WooCommerce Manual Moneris Payment UI (Direct API)
 * Description: Adds a slide-out panel for manual credit card processing via Moneris Direct API. WARNING: Captures raw CC data. Requires Moneris PHP SDK.
 * Version: 2.3
 * Author: spswoo
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * 1. Add the "Pay by Credit Card" button and slide-out panel
 */
add_action( 'woocommerce_order_item_add_action_buttons', 'wc_moneris_add_pay_ui', 10, 1 );
function wc_moneris_add_pay_ui( $order ) {
    $order = wc_get_order( $order );
    if ( ! $order ) return;

    $allowed_statuses = array( 'pending', 'on-hold', 'failed' );
    if ( ! in_array( $order->get_status(), $allowed_statuses ) ) return;
    if ( $order->get_transaction_id() ) return;
    if ( $order->get_total() <= 0 ) return;

    echo '<button type="button" class="button manual-pay-button">Pay by Credit Card</button>';
    ?>
    <div id="manual-pay-slideout" style="display:none; padding:15px; background:#f8f9f9; border-top:1px solid #e2e4e7; width:100%; box-sizing:border-box;">
        <h4 style="margin-top:0; padding-bottom:10px; border-bottom:1px solid #e2e4e7;">Manual Credit Card Payment (Moneris Direct)</h4>
        <input type="hidden" id="manual-pay-order-id" value="<?php echo esc_attr( $order->get_id() ); ?>" />
        <div class="manual-pay-fields" style="display:flex; gap:10px; margin:15px 0;">
            <input type="text" id="moneris-cc-num" placeholder="Card Number (No Spaces)" style="flex:2;" maxlength="20" />
            <input type="text" id="moneris-cc-exp" placeholder="MMYY" style="flex:1;" maxlength="5" />
            <input type="text" id="moneris-cc-cvc" placeholder="CVC" style="flex:1;" maxlength="4" />
        </div>
        <div id="monerisResponse" style="margin-bottom:15px; color:#d63638; font-weight:bold;"></div>
        <div class="manual-pay-actions" style="display:flex; justify-content:space-between; align-items:center;">
            <button type="button" class="button cancel-manual-pay">Cancel</button>
            <button type="button" class="button button-primary submit-manual-pay">Process Payment</button>
        </div>
    </div>
    <?php
}

/**
 * 2. Enqueue admin JS for slide-out panel and AJAX
 */
add_action( 'admin_footer', 'wc_moneris_admin_scripts' );
function wc_moneris_admin_scripts() {
    $screen = get_current_screen();
    if ( ! $screen || ! in_array( $screen->id, array( 'shop_order', 'woocommerce_page_wc-orders' ) ) ) return;
    ?>
    <script type="text/javascript">
        jQuery(document).ready(function($){
            function fixLayoutPlacement() {
                if ($('#manual-pay-slideout').length && $('.wc-order-bulk-actions').length) {
                    $('#manual-pay-slideout').insertAfter('.wc-order-bulk-actions');
                }
            }
            fixLayoutPlacement();

            $(document).on('click', '.manual-pay-button', function(e){
                e.preventDefault();
                fixLayoutPlacement();
                $('.wc-order-bulk-actions').hide();
                $('#manual-pay-slideout').slideDown();
            });

            $(document).on('click', '.cancel-manual-pay', function(e){
                e.preventDefault();
                $('#manual-pay-slideout').slideUp(250, function() { $('.wc-order-bulk-actions').show(); });
                $('#monerisResponse').text('');
                $('#moneris-cc-num, #moneris-cc-exp, #moneris-cc-cvc').val('');
            });

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

                $('#monerisResponse').css('color','#333').text('Processing directly with Moneris...');

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
                        if(response.success) {
                            $('#monerisResponse').css('color','green').text('Payment Complete! Reloading order...');
                            $('#moneris-cc-num, #moneris-cc-exp, #moneris-cc-cvc').val('');
                            setTimeout(function(){ location.reload(); },1500);
                        } else {
                            $('#monerisResponse').css('color','#d63638').text(response.data);
                        }
                    },
                    error: function() {
                        $('#monerisResponse').css('color','#d63638').text('Server error during processing.');
                    }
                });
            });
        });
    </script>
    <?php
}

/**
 * 3. Check if Moneris PHP SDK exists
 */
function wc_moneris_check_library() {
    $moneris_path = plugin_dir_path(__FILE__) . 'mpgClasses.php';
    if ( ! file_exists($moneris_path) ) {
        add_action('admin_notices', function(){
            echo '<div class="notice notice-error">';
            echo '<p><strong>WooCommerce Manual Moneris Payment UI:</strong> Missing <code>mpgClasses.php</code>. ';
            echo 'Please download the official <a href="https://developer.moneris.com/Documentation/PHP/Download" target="_blank">Moneris PHP SDK</a> and place it in the plugin folder.</p>';
            echo '</div>';
        });
        return false;
    }
    require_once $moneris_path;
    return true;
}

/**
 * 4. AJAX handler for processing manual payments
 */
add_action('wp_ajax_wc_moneris_process_manual_payment','wc_moneris_process_manual_payment');
function wc_moneris_process_manual_payment() {
    if( ! current_user_can('edit_shop_orders') ) {
        wp_send_json_error('Permission denied.');
    }

    if( ! wc_moneris_check_library() ) {
        wp_send_json_error('Moneris PHP library not found. Payment cannot be processed.');
    }

    $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
    $cc_num   = isset($_POST['cc_num']) ? sanitize_text_field($_POST['cc_num']) : '';
    $cc_exp   = isset($_POST['cc_exp']) ? sanitize_text_field($_POST['cc_exp']) : '';
    $cc_cvc   = isset($_POST['cc_cvc']) ? sanitize_text_field($_POST['cc_cvc']) : '';

    if( ! $order_id || ! $cc_num || ! $cc_exp ) {
        wp_send_json_error('Missing credit card information.');
    }

    $order = wc_get_order($order_id);
    if( ! $order ) wp_send_json_error('Invalid order.');

    // Format expiry
    $formatted_exp = $cc_exp;
    if(strlen($cc_exp) === 4){
        $month = substr($cc_exp,0,2);
        $year = substr($cc_exp,2,2);
        $cc_exp = $year.$month;
        $formatted_exp = $month.'/20'.$year;
    }

    $masked_cc = 'XXXX-'.substr($cc_num,-4);

    // MONERIS CREDENTIALS
    $store_id  = 'store5';   // Replace with your store ID
    $api_token = 'yesguy';   // Replace with your API token
    $test_mode = true;       // Set false for live

    $amount = number_format((float)$order->get_total(),2,'.','');

    // Build transaction
    $txnArray = array(
        'type'       => 'purchase',
        'order_id'   => $order->get_order_number().'-'.time(),
        'cust_id'    => $order->get_customer_id() ? $order->get_customer_id() : 'guest_order',
        'amount'     => $amount,
        'pan'        => $cc_num,
        'expdate'    => $cc_exp,
        'crypt_type' => '1'
    );
    $mpgTxn = new mpgTransaction($txnArray);

    if(!empty($cc_cvc)){
        $mpgCvdInfo = new mpgCvdInfo(array('cvd_indicator'=>'1','cvd_value'=>$cc_cvc));
        $mpgTxn->setCvdInfo($mpgCvdInfo);
    }

    $billing_postcode = $order->get_billing_postcode();
    $billing_address  = $order->get_billing_address_1();
    if(!empty($billing_postcode) || !empty($billing_address)){
        $mpgAvsInfo = new mpgAvsInfo(array(
            'avs_street_number'=>'',
            'avs_street_name'=>$billing_address,
            'avs_zipcode'=>str_replace(' ','',$billing_postcode)
        ));
        $mpgTxn->setAvsInfo($mpgAvsInfo);
    }

    $mpgRequest = new mpgRequest($mpgTxn);
    $mpgRequest->setProcCountryCode('CA');
    $mpgRequest->setTestMode($test_mode);

    $mpgHttpPost = new mpgHttpsPost($store_id,$api_token,$mpgRequest);
    $mpgResponse = $mpgHttpPost->getMpgResponse();

    $response_code = $mpgResponse->getResponseCode();
    $message       = $mpgResponse->getMessage();
    $receipt_id    = $mpgResponse->getReceiptId();

    if($response_code !== 'null' && (int)$response_code < 50){
        $card_map = array('V'=>'Visa','M'=>'Mastercard','AX'=>'American Express','NO'=>'Discover','C'=>'JCB');
        $card_type = isset($card_map[$mpgResponse->getCardType()]) ? $card_map[$mpgResponse->getCardType()] : $mpgResponse->getCardType();
        $avs_result = $mpgResponse->getAvsResultCode() ?: 'Not Provided';
        $cvd_result = $mpgResponse->getCvdResultCode() ?: 'Not Provided';

        $note = "Moneris Manual Payment Approved\n";
        $note .= "Credit Card Type: ".$card_type."\n";
        $note .= "Credit Card Number: ".$masked_cc."\n";
        $note .= "Expiration Date: ".$formatted_exp."\n";
        $note .= "CVV Response: ".$cvd_result."\n";
        $note .= "AVS Response: ".$avs_result."\n";
        $note .= "Transaction Id: ".$receipt_id."\n";
        $note .= "Authorization Code: ".$mpgResponse->getAuthCode()."\n";
        $note .= "VBV/3DS Chargeback Liability Shift: No (Manual Entry)\n";
        $note .= "Order was placed using ".$order->get_currency();

        $order->payment_complete($receipt_id);
        $order->add_order_note($note);

        wp_send_json_success('Payment successful!');
    } else {
        $order->add_order_note(sprintf('Moneris manual Direct API payment Failed. Code: %s. Message: %s',$response_code,$message));
        wp_send_json_error('Declined: '.$message);
    }
}