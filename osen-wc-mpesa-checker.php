<?php

/**
 * @package Mpesa Checker
 * @version 1.20.4
 */
/*
Plugin Name: Mpesa Transaction Checker
Plugin URI: http://wordpress.org/plugins/hello-dolly/
Description: This is not just a plugin, it symbolizes the hope and enthusiasm of an entire generation summed up in two words sung most famously by Louis Armstrong: Hello, Dolly. When activated you will randomly see a lyric from <cite>Hello, Dolly</cite> in the upper right of your admin screen on every page.
Author: Mauko Maunde
Version: 1.20.4
Author URI: http://ma.tt/
*/

// Add Shortcode
function mpesa_checker($atts)
{
    // Attributes
    $atts = shortcode_atts(
        array(
            'form-class' => 'form',
            'input-class' => 'form-control',
            'form-group-class' => 'form-group',
            'button-class' => 'btn btn-primary',
        ),
        $atts,
        'mpesa-checker'
    );

    $form_class = $atts['form-class'];
    $input_class = $atts['input-class'];
    $form_group_class = $atts['form-group-class'];
    $button_class = $atts['button-class'];

    $ajax_url =  admin_url('admin-ajax.php');
    $nonce = wp_nonce_field('process_mpesa_check', 'mpesa_check_nonce', true, false);

    if (!current_user_can('edit_pages')) {
        return "You're not allowed to access this page";
    }

    return <<<FORM
    <form class="$form_class" id="mpesa-checker-form" action="$ajax_url">
        <div class="$form_group_class">
            <input name="phone" class="$input_class" placeholder="Phone Number(Start with 254)">
        </div>
        <div class="$form_group_class">
            <input type="hidden" name="action" value="process_mpesa_check">
            $nonce
            <button class="$button_class" type="submit">Check Transactions</button>
        </div>
    </form>
    
    <div id="mpesa-results-table-body">
    </div>
FORM;
}
add_shortcode('mpesa-checker', 'mpesa_checker');

add_action('wp_footer', 'check_mpesa_ajax');
function check_mpesa_ajax()
{
    echo <<<SCRIPT
        <script id="mpesa-checker-form-script">
            jQuery(function($) {
                //https://codebriefly.com/complete-datatable-setup/

                // $("#mpesa-results-table").DataTable({
                //     paging: false,
                //     "bInfo" : false,
                //     "bSort": false,
                //     buttons: [
                //         'copy', 'csv', 'excel', 'pdf', 'print'
                //     ]
                // });

                $('#mpesa-checker-form').submit(function(e){
                    e.preventDefault();

                    var form = $(this);
                    $.post(form.attr('action'), form.serialize(), function(results) {
                        $("#mpesa-results-table").removeAttr('style');

                        if(results.data){
                            $("#mpesa-results-table-body").html(results.data);
                        } else {
                            $.each(results.data, function(id, post){
                                $("#mpesa-results-table-body").html('<tr><td colspan="5">An error occured</td></tr>');
                            });
                        }
                    });
                });
            });
        </script>
SCRIPT;
}

add_action('wp_ajax_nopriv_process_mpesa_check', 'process_mpesa_check');
add_action('wp_ajax_process_mpesa_check', 'process_mpesa_check');
function process_mpesa_check()
{
    if (!isset($_POST['mpesa_check_nonce']) || !wp_verify_nonce($_POST['mpesa_check_nonce'], 'process_mpesa_check')) {
        wp_send_json_error('<center>Form is not valid</center>');
    }

    if (!isset($_POST['phone']) || empty($_POST['phone'])) {
        wp_send_json_error('<center>Please input a phone number</center>');
    }

    $phone = preg_replace('/\s+/', '', $_POST['phone']);
    $phone     = (substr($phone, 0, 1) == '0') ? preg_replace('/^0/', '254', $phone) : $phone;
    $phone     = (substr($phone, 0, 1) == '+') ? preg_replace('/^+/', '', $phone) : $phone;
    $the_phone = (substr($phone, 0, 1) == '7') ? "254{$phone}" : $phone;

    if (strlen($the_phone) !== 12) {
        wp_send_json_error('<center>Please input a valid phone number</center>');
    }

    // $transactions = get_posts(
    //     [
    //         'post_type' => 'mpesaipn',
    //         'meta_query' => array(
    //             array(
    //                 'key' => '_phone',
    //                 'value' => $the_phone,
    //                 'compare' => '=',
    //             )
    //         )
    //     ]
    // );

    global $wpdb;
    $transactions = $wpdb->get_results("SELECT * FROM `" . $wpdb->postmeta . "` WHERE meta_key='_phone' AND meta_value like '%" . $the_phone . "%' ORDER BY post_id DESC LIMIT 5");

    foreach ($transactions as $item) {

        $results .= '<hr><table style="width: 100%;">';

        $post = get_post($item->post_id);
        $date      = get_the_date('D M j, Y h:ia', $post->ID);
        $receipt      = get_post_meta($post->ID, '_receipt', true);
        $customer      = get_post_meta($post->ID, '_customer', true);
        $amount      = get_post_meta($post->ID, '_amount', true);

        $results .= <<<ROW
            <tr>
                <th>Date:</th>
                <td>$date</td>
            </tr>
            <tr>
                <th>Receipt:</th>
                <td>$receipt</td>
            </tr>
            <tr>
                <th>Customer:</th>
                <td>$customer</td>
            </tr>
            <tr>
                <th>Amount:</th>
                <td>$amount</td>
            </tr>
ROW;

        $results .= '</table>';
    }

    $count = count($transactions);
    ///$results .= "<tr><th scope='row' colspan='4'>TOTAL TRANSACTIONS</th><td>$count</td></tr>";

    if ($count > 0) {
        wp_send_json_success($results);
    } else {
        wp_send_json_error("<center>No transactions found for <b>$the_phone</b>.</center>");
    }
}

function datatables_scripts_in_head()
{
    wp_enqueue_script('datatables', 'https://cdn.datatables.net/v/dt/dt-1.10.20/datatables.min.js', array('jquery'));
    wp_localize_script('datatables', 'datatablesajax', array('url' => admin_url('admin-ajax.php')));
    wp_enqueue_script('dtbuttons', 'https://cdn.datatables.net/buttons/1.6.1/js/dataTables.buttons.min.js', array('jquery'));
    wp_enqueue_style('datatables', 'https://cdn.datatables.net/v/dt/dt-1.10.20/datatables.min.css');
}
add_action('wp_enqueue_scripts', 'datatables_scripts_in_head');
