<?php
/*
  canpar.php,v 0.1 2006/07/22 10:52:11 hpdl Exp $

  Released under the GNU General Public License

  ORIGINAL CANPAR SCRIPT
  Copyright (c) 2006 J. B. Wallace (jbwallace@shaw.ca) 2006.7.22

  INTEGRATION WITH XML
  Copyright (c) 2006 K. B. Gervais (kevinalwayswins@hotmail.com) 2006.8.25
  Adaption copyright CyKron Interactive (www.cykron.com).

  MODIFICATION TO WORK WITH ZEN CART
  Copyright (c) 2007 Steve Oliveira (oliveira.steve@gmail.com) 7/24/2007

  MODIFICATION TO WORK WITH ZEN CART 1.5.4 and work with markup, fuel surcharge and box quantity count.

Updated by: bislewl - 6/15/2016 (v1.5.2)
- Added debug display
- Fixed zero weight issue. (minimum 0.1 weight now)


Updated by: bislewl - 6/13/2016 (v1.5.1)
- Added ability to select the services you wanted to offer versus just Ground and USA
- Added ability to return custom quotes
- Fixed Markup Bug
- Improved markup to allow % or set $ amount.
- Fixed issue where was allowing customer to checkout with Shipping total of $0.00 with bad address
- Fixed Tax Calculation issues
- Fixed Zone Issues
- Added pickup tag option
- Added Ability to select weight unit

*/

class canpar
{
    var $code, $title, $description, $icon, $enabled;

// class constructor
    function canpar()
    {
        global $order, $db;
        $this->code = 'canpar';
        $this->title = MODULE_SHIPPING_CANPAR_TEXT_TITLE;
        $this->description = MODULE_SHIPPING_CANPAR_TEXT_DESCRIPTION;
        $this->mark_up = MODULE_SHIPPING_CANPAR_MARK_UP;
        $this->sort_order = MODULE_SHIPPING_CANPAR_SORT_ORDER;
        $this->icon = DIR_WS_IMAGES . 'icons/canpar.gif';
        $this->tax_class = MODULE_SHIPPING_CANPAR_TAX_CLASS;
        $this->tax_basis = MODULE_SHIPPING_CANPAR_TAX_BASIS;
        $this->enabled = ((MODULE_SHIPPING_CANPAR_STATUS == 'True') ? true : false);
        $this->weight_unit = substr(MODULE_SHIPPING_CANPAR_WEIGHT_UNIT, 1);
        $this->pick_up_tag = MODULE_SHIPPING_CANPAR_PICKUP_TAG;
        $this->rate_type = MODULE_SHIPPING_CANPAR_RATE_TYPE;
        $this->debug_display = ((MODULE_SHIPPING_CANPAR_DEBUG_DISPLAY == 'True') ? true : false);

        if (($this->enabled == true) && ((int)MODULE_SHIPPING_CANPAR_ZONE > 0)) {
            $check_flag = false;
            $check = $db->Execute("select zone_id from " . TABLE_ZONES_TO_GEO_ZONES . " where geo_zone_id = '" . MODULE_SHIPPING_CANPAR_ZONE . "' and zone_country_id = '" . $order->delivery['country']['id'] . "' order by zone_id");
            while (!$check->EOF) {
                if ($check->fields['zone_id'] < 1) {
                    $check_flag = true;
                    break;
                } elseif ($check->fields['zone_id'] == $order->delivery['zone_id']) {
                    $check_flag = true;
                    break;
                }
                $check->MoveNext();
            }

            if ($check_flag == false) {
                $this->enabled = false;
            }
        }

        $offered_service_types = explode(',', MODULE_SHIPPING_CANPAR_SERVICE_TYPES);
//        echo var_dump($offered_service_types);
        $this->service_types = array();
        foreach ($offered_service_types as $service_type) {
            $service_type_id = substr(ltrim($service_type, ' '), 0, 1);
            if ($order->delivery['country']['id'] == 38) {
                switch ($service_type_id) {
                    case '1':
                        $this->service_types[] = array('id' => '1', 'title' => MODULE_SHIPPING_CANPAR_GROUND_DESCRIPTION);
                        break;
                    case '3':
                        $this->service_types[] = array('id' => '3', 'title' => MODULE_SHIPPING_CANPAR_SELECT_LETTER_DESCRIPTION);
                        break;
                    case '4':
                        $this->service_types[] = array('id' => '4', 'title' => MODULE_SHIPPING_CANPAR_SELECT_PAK_DESCRIPTION);
                        break;
                    case '5':
                        $this->service_types[] = array('id' => '5', 'title' => MODULE_SHIPPING_CANPAR_SELECT_PARCEL_DESCRIPTION);
                        break;
                    case 'C':
                        $this->service_types[] = array('id' => 'C', 'title' => MODULE_SHIPPING_CANPAR_OVERNIGHT_LETTER_DESCRIPTION);
                        break;
                    case 'D':
                        $this->service_types[] = array('id' => 'D', 'title' => MODULE_SHIPPING_CANPAR_OVERNIGHT_PAK_DESCRIPTION);
                        break;
                    case 'E':
                        $this->service_types[] = array('id' => 'E', 'title' => MODULE_SHIPPING_CANPAR_OVERNIGHT_PARCEL_DESCRIPTION);
                        break;
                }
            }
            if ($order->delivery['country']['id'] == 223) {
                switch ($service_type_id) {
                    case '2':
                        $this->service_types[] = array('id' => '2', 'title' => MODULE_SHIPPING_CANPAR_USA_DESCRIPTION);
                        break;
                    case 'F':
                        $this->service_types[] = array('id' => 'H', 'title' => MODULE_SHIPPING_CANPAR_USA_SELECT_LETTER_DESCRIPTION);
                        break;
                    case 'G':
                        $this->service_types[] = array('id' => 'H', 'title' => MODULE_SHIPPING_CANPAR_USA_SELECT_PAK_DESCRIPTION);
                        break;
                    case 'H':
                        $this->service_types[] = array('id' => 'H', 'title' => MODULE_SHIPPING_CANPAR_USA_SELECT_PARCEL_DESCRIPTION);
                        break;
                }
            }
        }
    }

// class methods
    function quote($method = '')
    {
        global $order;
        $methods = array();
        if (count($this->service_types) > 0) {
            foreach ($this->service_types as $service_type) {
                $returned_quote = $this->get_quote($service_type['id']);
                if ($returned_quote > 0) {
                    $methods[] = array('cost' => $returned_quote,
                        'id' => $service_type['id'],
                        'title' => $service_type['title']);
                }
            }
        }

        if (isset($methods) && count($methods) > 1) {
            sort($methods);
            $this->quotes = array('id' => $this->code,
                'module' => MODULE_SHIPPING_CANPAR_TEXT_TITLE,
                'info' => $this->info());
            $this->quotes['methods'] = $methods;

            if ($this->tax_class > 0) {
                $this->quotes['tax'] = zen_get_tax_rate($this->tax_class, $order->delivery['country']['id'], $order->delivery['zone_id']);
            }
            if (zen_not_null($this->icon)) $this->quotes['icon'] = zen_image($this->icon, $this->title, null, null, 'align="middle"');

        } else {
            $this->quotes = array('module' => $this->title,
                'error' => MODULE_SHIPPING_CANPAR_ERROR_NO_QUOTE);
        }
        return $this->quotes;
    }

    function calc_markup($shipping_cost)
    {
        $mark_up = 0;
        if ($shipping_cost == 0) {
            return $mark_up;
        }
        if (strpos(MODULE_SHIPPING_CANPAR_MARK_UP, '%') !== false) {
            $markup_percent = str_replace('%', '', MODULE_SHIPPING_CANPAR_MARK_UP);
            if (MODULE_SHIPPING_CANPAR_MARK_UP > 0) {
                $mark_up = $shipping_cost * ($markup_percent / 100);
            }
        } else {
            if (MODULE_SHIPPING_CANPAR_MARK_UP > 0) {
                $mark_up = MODULE_SHIPPING_CANPAR_MARK_UP;
            }
        }
//        echo '('.$shipping_cost.'|'.$mark_up.')  ';
        return $mark_up;
    }

    function get_quote($service_type = '1')
    {
        global $order, $shipping_weight, $shipping_num_boxes, $db;
        if ($shipping_weight < 0.1) {
            $shipping_weight = 0.1;
        }
        $srcFSA = substr(strtoupper(SHIPPING_ORIGIN_ZIP), 0, 7);
        $desFSA = substr(strtoupper($order->delivery['postcode']), 0, 7);
        $srcFSA = str_replace(" ", "", $srcFSA);
        $desFSA = str_replace(" ", "", $desFSA);
        $total_count = $_SESSION['cart']->count_contents();

        //Connect to CanPar here to get quote, and parse XML.
        if ($this->rate_type == 'Custom Rate') {
            // An example of a URL with shipment data, for account number 99999999 with a token of CANPAR is:
            // https://www.canpar.com/XML/RatingXML.jsp?shipment=<shipment weight_system="IMPERIAL" shipper_number="99999999" destination_postal_code="M1M1M1" service_type="1"><total total_pieces="2" total_weight="20"/></shipment>&token=CANPAR
            $url_request = 'https://www.canpar.com/XML/RatingXML.jsp?shipment=<shipment weight_system="IMPERIAL" shipper_number="' . MODULE_SHIPPING_CANPAR_ACCOUNT_NUMBER . '" destination_postal_code="' . $desFSA . '" service_type="' . $service_type . '"><total total_pieces="' . $total_count . '" total_weight="' . (float)$this->weight_unit . '"/></shipment>&token=' . MODULE_SHIPPING_CANPAR_ACCESS_TOKEN;
            $body = file_get_contents($url_request);
            if ($body == 'Access denied') {
                $db->Execute("UPDATE " . TABLE_CONFIGURATION . " SET configuration_value = 'Base Rate' WHERE configuration_key = 'MODULE_SHIPPING_CANPAR_RATE_TYPE'");
            }
            $xml = new SimpleXMLElement($body);
            $json = json_encode($xml);
            $rating_array = json_decode($json, TRUE);
            $shipping_cost = $rating_array['rate']["@attributes"]['total_charge'];

            $mark_up = $this->calc_markup($shipping_cost);
            $shipping_cost = $shipping_cost + $mark_up;

        } else {
            $request = join('&', array('service=' . $service_type,
                'quantity=' . $shipping_num_boxes,
                'unit=' . $this->weight_unit,
                'origin=' . $srcFSA,
                'dest=' . $desFSA,
                'cod=0',
                'weight=' . (float)$shipping_weight,
                'put=' . $this->pick_up_tag,
                'xc=0',
                'dec=0'));
            $url_request = 'http://www.canpar.com/CanparRateXML/BaseRateXML.jsp?' . $request;
            $body = file_get_contents($url_request);
            $xml = new SimpleXMLElement($body);
            $json = json_encode($xml);
            $rating_array = json_decode($json, TRUE);
            $shipping_cost = $rating_array['CanparCharges']['BaseRate'];

            $mark_up = $this->calc_markup($shipping_cost);

            if (MODULE_SHIPPING_CANPAR_FUEL_SURCHARGE > 0) {
                $FuelSurcharge = $shipping_cost * (MODULE_SHIPPING_CANPAR_FUEL_SURCHARGE / 100);
            } else {
                $FuelSurcharge = 0;
            }
            $shipping_cost = $shipping_cost + $FuelSurcharge + $mark_up;
        }
        if ($this->debug_display == 'True') {
            echo $url_request . "\n";
        }
        return $shipping_cost;
    }

    function info()
    {
        $info_description = MODULE_SHIPPING_CANPAR_INFO_DESCRIPTION;
        return $info_description;
    }

    function check()
    {
        global $db;
        $check_query = $db->Execute("SELECT configuration_value FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = 'MODULE_SHIPPING_CANPAR_STATUS'");
        $this->check = $check_query->RecordCount();
        return $this->check;
    }

    function install()
    {
        global $db;
        $services_offered = array();
        $services_offered[] = '1 - Ground';
        $services_offered[] = '2 - USA';
        $services_offered[] = '3 - Select Letter';
        $services_offered[] = '4 - Select Pak';
        $services_offered[] = '5 - Select Parcel';
        $services_offered[] = 'C - Overnight Letter';
        $services_offered[] = 'D - Overnight Pak';
        $services_offered[] = 'E - Overnight Parcel';
        $services_offered[] = 'F - USA Select Letter';
        $services_offered[] = 'G - USA Select Pak';
        $services_offered[] = 'H - USA Select Parcel';
        $services_offered_string = '';
        foreach ($services_offered as $service_type) {
            $services_offered_string .= "\'" . $service_type . "\', ";
        }
        trim($services_offered_string, ',');
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) VALUES ('Enable CANPAR Shipping', 'MODULE_SHIPPING_CANPAR_STATUS', 'True', 'Do you want to offer CANPAR rate shipping?', '6', '1', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('Mark Up', 'MODULE_SHIPPING_CANPAR_MARK_UP', '0%', 'Use the following mark-up on the shipping list fees. <br/> end with % for a percentage or without for a certain amount', '6', '2', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('Fuel Surcharge Rate', 'MODULE_SHIPPING_CANPAR_FUEL_SURCHARGE', '16.8', 'KEEP UP TO DATE: Fuel Surcharge Rate Enter as a percentage without the % sign (eg. 16.8). <br/>Only Applied on base rate. Custom Rate included the Fuel Surcharge', '6', '3', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) VALUES ('Weight Units', 'MODULE_SHIPPING_CANPAR_WEIGHT_UNIT', 'LBS', 'Weight Units:', '6', '4', 'zen_cfg_select_option(array(\'LBS\', \'KGS\'), ', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Pickup Tag', 'MODULE_SHIPPING_CANPAR_PICKUP_TAG', '0', 'Pickup Tag Type:<br/><b>1</b> - Pickup Tag Shipment</br> <b>0</b> - Not a Pickup Tag Shipment<br/>', '6', '5', 'zen_cfg_select_option(array(\'1\',\'0\'),', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Services Offered', 'MODULE_SHIPPING_CANPAR_SERVICE_TYPES', '', 'Services Selected', '6', '6', 'zen_cfg_select_multioption(array(" . $services_offered_string . "),', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('CanPar Account Number', 'MODULE_SHIPPING_CANPAR_ACCOUNT_NUMBER', '', 'Enter Account Number', '6', '7', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('CanPar Access Token', 'MODULE_SHIPPING_CANPAR_ACCESS_TOKEN', '', 'Enter Access Token', '6', '8', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) VALUES ('Rate Trype', 'MODULE_SHIPPING_CANPAR_RATE_TYPE', 'Base Rate', 'Rate Type: <br/> Note: You must have correct Shipper Number and Token to access Account Rates', '6', '9', 'zen_cfg_select_option(array(\'Base Rate\', \'Customer Rate\'), ', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) VALUES ('Shipping Zone', 'MODULE_SHIPPING_CANPAR_ZONE', '0', 'If a zone is selected, only enable this shipping method for that zone.', '6', '10', 'zen_get_zone_class_title', 'zen_cfg_pull_down_zone_classes(', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) VALUES ('Tax Class', 'MODULE_SHIPPING_CANPAR_TAX_CLASS', '0', 'Use the following tax class on the shipping fee.', '6', '11', 'zen_get_tax_class_title', 'zen_cfg_pull_down_tax_classes(', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) VALUES ('Tax Basis', 'MODULE_SHIPPING_CANPAR_TAX_BASIS', 'Shipping', 'On what basis is Shipping Tax calculated. Options are<br />Shipping - Based on customers Shipping Address<br />Billing Based on customers Billing address<br />Store - Based on Store address if Billing/Shipping Zone equals Store zone', '6', '12', 'zen_cfg_select_option(array(\'Shipping\', \'Billing\', \'Store\'), ', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('Sort Order', 'MODULE_SHIPPING_CANPAR_SORT_ORDER', '0', 'Sort order of display.', '6', '13', now())");
        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) VALUES ('Enable CANPAR Shipping Debug', 'MODULE_SHIPPING_CANPAR_DEBUG_DISPLAY', 'False', 'Do you want the module to display the URLs it\'s requesting??', '6', '15', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");
    }

    function remove()
    {
        global $db;
        $db->Execute("DELETE FROM " . TABLE_CONFIGURATION . " WHERE configuration_key in ('" . implode("', '", $this->keys()) . "')");
    }

    function keys()
    {


        $keys[] = 'MODULE_SHIPPING_CANPAR_STATUS';
        $keys[] = 'MODULE_SHIPPING_CANPAR_TAX_CLASS';
        $keys[] = 'MODULE_SHIPPING_CANPAR_MARK_UP';
        $keys[] = 'MODULE_SHIPPING_CANPAR_SORT_ORDER';
        $keys[] = 'MODULE_SHIPPING_CANPAR_FUEL_SURCHARGE';
        $keys[] = 'MODULE_SHIPPING_CANPAR_WEIGHT_UNIT';
        $keys[] = 'MODULE_SHIPPING_CANPAR_PICKUP_TAG';
        $keys[] = 'MODULE_SHIPPING_CANPAR_ACCOUNT_NUMBER';
        $keys[] = 'MODULE_SHIPPING_CANPAR_ACCESS_TOKEN';
        $keys[] = 'MODULE_SHIPPING_CANPAR_RATE_TYPE';
        $keys[] = 'MODULE_SHIPPING_CANPAR_ZONE';
        $keys[] = 'MODULE_SHIPPING_CANPAR_TAX_BASIS';
        $keys[] = 'MODULE_SHIPPING_CANPAR_SERVICE_TYPES';
        $keys[] = 'MODULE_SHIPPING_CANPAR_DEBUG_DISPLAY';

        return $keys;

    }
}

