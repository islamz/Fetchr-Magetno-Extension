<?php
/**
* Fetchr.
*
* NOTICE OF LICENSE
*
* This source file is subject to the Open Software License (OSL 3.0)
* It is also available through the world-wide-web at this URL:
* https://fetchr.zendesk.com/hc/en-us/categories/200522821-Downloads
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to ws@fetchr.us so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade Fetchr Magento Extension to newer
* versions in the future. If you wish to customize Fetchr Magento Extension (Fetchr Shipping) for your
* needs please refer to http://www.fetchr.us for more information.
*
* @author     Islam Khalil
* @copyright  Copyright (c) 2015 Fetchr (http://www.fetchr.us)
* @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
*/
class Fetchr_Shipping_Model_Observer{

    public function getCCTrackingNo($observer) {
        //Check IF the Auto Push Is Enabled
        $autoCCPush     = Mage::getStoreConfig('carriers/fetchr/autoccpush');
        $invoice        = $observer->getEvent()->getInvoice();
        $order          = $invoice->getOrder();
        $paymentType    = $order->getPayment()->getMethodInstance()->getCode();
        
        if(strstr($paymentType, 'paypal')){
            $paymentType = 'paypal';
        }
        switch ($paymentType) {
            case 'cashondelivery':
            case 'phoenix_cashondelivery':
                $paymentType    = 'COD';
            break;
            case 'ccsave':
            case 'paypal':
                $paymentType    = 'CCOD';
            break;
            default:
                $paymentType    = 'cd';
            break;
        }

        if($autoCCPush == true && ($paymentType == 'CCOD' || $paymentType == 'cd') ){
            return $this->pushCCOrder($order);
        }
    }

    public function getCODTrackingNo($observer) {
        //Check IF the Auto Push Is Enabled
        $autoCODPush    = Mage::getStoreConfig('carriers/fetchr/autocodpush');
        $order          = $observer->getEvent()->getOrder();
        $paymentType    = $order->getPayment()->getMethodInstance()->getCode();
        
        if(strstr($paymentType, 'paypal')){
            $paymentType = 'paypal';
        }
        switch ($paymentType) {
            case 'cashondelivery':
            case 'phoenix_cashondelivery':
                $paymentType    = 'COD';
            break;
            case 'ccsave':
            case 'paypal':
                $paymentType    = 'CCOD';
            break;
            default:
                $paymentType    = 'cd';
            break;
        }
        if($autoCODPush == true && $paymentType == 'COD'){
            return $this->pushCODOrder($order);
        }
    }

    public function pushOrderAfterShipmentCreation($observer)
    {
        $shipment          = $observer->getEvent()->getShipment();
        $order             = $shipment->getOrder();
        $collection        = Mage::getModel('sales/order')->loadByIncrementId($order->getIncrementId());
        $shippingmethod    = $order->getShippingMethod();
        $paymentType       = $order->getPayment()->getMethodInstance()->getCode();
        $autoCODPush       = Mage::getStoreConfig('carriers/fetchr/autocodpush');
        $autoCCPush        = Mage::getStoreConfig('carriers/fetchr/autoccpush');
        
        if(strstr($paymentType, 'paypal')){
            $paymentType = 'paypal';
        }
        switch ($paymentType) {
            case 'cashondelivery':
            case 'phoenix_cashondelivery':
                $paymentType    = 'COD';
            break;
            case 'ccsave':
            case 'paypal':
                $paymentType    = 'CCOD';
            break;
            default:
                $paymentType    = 'cd';
            break;
        }

        if ( $shippingmethod == 'fetchr_fetchr' && $paymentType == 'COD' && $autoCODPush == false){
            return $this->pushCODOrder($order, $shipment);
        }elseif($shippingmethod == 'fetchr_fetchr' && ($paymentType == 'CCOD' || $paymentType == 'cd') && $autoCCPush == false){
            return $this->pushCCOrder($order, $shipment);
        }
    }

    protected function pushCODOrder($order, $shipment='' )
    {
        $collection         = Mage::getModel('sales/order')->loadByIncrementId($order->getIncrementId());
        $store              = Mage::app()->getStore();
        $storeTelephone     = Mage::getStoreConfig('general/store_information/phone');
        $storeAddress       = Mage::getStoreConfig('general/store_information/address');
        $shippingmethod     = $order->getShippingMethod();
        $paymentType        = 'COD';

        if ($collection->getData() && $shippingmethod == 'fetchr_fetchr' && $paymentType == 'COD') {
            
            $resource = Mage::getSingleton('core/resource');
            $adapter = $resource->getConnection('core_read');
            try {
                foreach ($order->getAllVisibleItems() as $item) {
                    if ($item['product_type'] == 'configurable') {
                        $itemArray[] = array(
                            'client_ref' => $order->getIncrementId(),
                            'name' => $item['name'],
                            'sku' => $item['sku'],
                            'quantity' => $item['qty_ordered'],
                            'merchant_details' => array(
                                'mobile' => $storeTelephone,
                                'phone' => $storeTelephone,
                                'name' => $store->getFrontendName(),
                                'address' => $storeAddress,
                            ),
                            'COD' => $order->getShippingAmount(),
                            'price' => $item['price'],
                            'is_voucher' => 'No',
                        );
                    } else {
                        $itemArray[] = array(
                            'client_ref' => $order->getIncrementId(),
                            'name' => $item['name'],
                            'sku' => $item['sku'],
                            'quantity' => $item['qty_ordered'],
                            'merchant_details' => array(
                                'mobile' => $storeTelephone,
                                'phone' => $storeTelephone,
                                'name' => $store->getFrontendName(),
                                'address' => $storeAddress,
                            ),
                            'COD' => $order->getShippingAmount(),
                            'price' => $item['price'],
                            'is_voucher' => 'No',
                        );
                    }
                }
                $discountAmount = 0;
                if ($order->getDiscountAmount()) {
                    $discountAmount = abs($order->getDiscountAmount());
                }
                
                $address        = $order->getShippingAddress()->getData();
                $grandtotal     = $order->getGrandTotal();
                $discount       = $discountAmount;

                $this->serviceType  = Mage::getStoreConfig('carriers/fetchr/servicetype');
                $this->userName     = Mage::getStoreConfig('carriers/fetchr/username');
                $this->password     = Mage::getStoreConfig('carriers/fetchr/password');
                $ServiceType        = $this->serviceType;
                
                switch ($ServiceType) {
                    case 'fulfilment':
                    $dataErp[] = array(
                        'order' => array(
                            'items' => $itemArray,
                            'details' => array(
                                'status' => '',
                                'discount' => $discount,
                                'grand_total' => $grandtotal,
                                'customer_email' => $order->getCustomerEmail(),
                                'order_id' => $order->getIncrementId(),
                                'customer_firstname' => $address['firstname'],
                                'payment_method' => $paymentType,
                                'customer_mobile' => $address['telephone'],
                                'customer_lastname' => $address['lastname'],
                                'order_country' => $address['country_id'],
                                'order_address' => $address['street'].', '.$address['city'].', '.$address['country_id'],
                            ),
                        ),
                    );
                    break;
                    case 'delivery':
                    $dataErp = array(
                        'username' => $this->userName,
                        'password' => $this->password,
                        'method' => 'create_orders',
                        'pickup_location' => $storeAddress,
                        'data' => array(
                            array(
                                'order_reference' => $order->getIncrementId(),
                                'name' => $address['firstname'].' '.$address['lastname'],
                                'email' => $order->getCustomerEmail(),
                                'phone_number' => $address['telephone'],
                                'address' => $address['street'],
                                'city' => $address['city'],
                                'payment_type' => $paymentType,
                                'amount' => $grandtotal,
                                'description' => 'No',
                                'comments' => 'No',
                            ),
                        ),
                    );
                }

                $result[$order->getIncrementId()]['request_data'] = $dataErp;
                $result[$order->getIncrementId()]['response_data'] = $this->_sendDataToErp($dataErp, $order->getIncrementId());
                
                $response = $result[$order->getIncrementId()]['response_data'];
                $comments = '';

                // Setting The Comment in the Order view
                if($ServiceType == 'fulfilment' ){

                    $tracking_number    = $response['response']['tracking_no'];
                    $response['status'] = ($response['success'] == true ? 'success' : 'faild');
                    
                    if($response['awb'] == 'SKU not found'){
                        $comments  .= '<strong>Fetchr Comment:</strong> One Of The SKUs Are Not Added to Fetchr System, Please Contact one of Fetchr\'s Account Managers for More Details';
                        $order->setStatus('pending');
                        $order->addStatusHistoryComment($comments, false);
                    }else{
                        $comments  .= '<strong>Fetchr Status: Tracking URL </strong> http://track.menavip.com/track.php?tracking_number='.$tracking_number;
                        $order->setStatus('processing');
                        $order->addStatusHistoryComment($comments, false);
                    }

                }elseif ($ServiceType == 'delivery') {
                    $tracking_number    = $response[key($response)];
                    $comments  .= '<strong>Fetchr Status: Tracking URL </strong> http://track.menavip.com/track.php?tracking_number='.$tracking_number;
                    $order->setStatus('processing');
                    $order->addStatusHistoryComment($comments, false);
                }
                
                //COD Order Shipping And Invoicing
                if($response['status'] == 'success'){
                    try {
                        //Get Order Qty
                        $qty = array();
                        foreach ($order->getAllVisibleItems() as $item) {
                            $product_id             = $item->getProductId();
                            $Itemqty                = $item->getQtyOrdered() - $item->getQtyShipped() - $item->getQtyRefunded() - $item->getQtyCanceled();
                            $qty[$item->getId()]    = $Itemqty;
                        }

                        //Invoicing
                        if($order->canInvoice()) {
                            $invoice = Mage::getModel('sales/service_order', $order)->prepareInvoice();
                            $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE);
                            $invoice->setState(Mage_Sales_Model_Order_Invoice::STATE_OPEN)->save();
                            
                            $transactionSave = Mage::getModel('core/resource_transaction')
                            ->addObject($invoice)
                            ->addObject($invoice->getOrder());
         
                            $transactionSave->save();
                            Mage::log('Order '.$orderId.' has been invoiced!', null, 'fetchr.log');
                        }else{
                            Mage::log('Order '.$orderId.' cannot be invoiced!', null, 'fetchr.log');
                        }

                        //Create Shipment When Auto Push Is OFF
                        if(!empty($shipment)){
                            //$shipment = $shipment->getData();
                            $trackdata = array();
                            $trackdata['carrier_code'] = 'fetchr';
                            $trackdata['title'] = 'Fetchr';
                            $trackdata['number'] = $tracking_number;
                            $track = Mage::getModel('sales/order_shipment_track')
                                ->setShipment($shipment)
                                ->setData('title', $trackdata['title']) // User syntax correct name here
                                ->setData('number', $trackdata['number'])
                                ->setData('carrier_code', $trackdata['carrier_code']) // use code that matches DB code for ship method here
                                ->setData('order_id', $shipment->getData('order_id'));

                            $track->save();
                        }

                        //Create Shipment When Auto Push Is ON
                        if ($order->canShip()) {
                            $shipment = $order->prepareShipment($qty);

                            $trackdata = array();
                            $trackdata['carrier_code'] = 'fetchr';
                            $trackdata['title'] = 'Fetchr';
                            $trackdata['number'] = $tracking_number;
                            $track = Mage::getModel('sales/order_shipment_track')->addData($trackdata);
                            
                            $shipment->addTrack($track);
                            //$shipment->register();
                            $transactionSave = Mage::getModel('core/resource_transaction')
                            ->addObject($shipment)
                            ->addObject($shipment->getOrder())
                            ->save();

                            Mage::log('Order '.$orderId.' has been shipped!', null, 'fetchr.log');
                        } else {
                            Mage::log('Order '.$orderId.' cannot be shipped!', null, 'fetchr.log');
                        }
                        
                    }catch (Exception $e) {
                        $order->addStatusHistoryComment('Exception occurred during automaticallyInvoiceShipCompleteOrder action. Exception message: '.$e->getMessage(), false);
                        $order->save();
                    }  
                }
                //End COD Order Shipping And Invoicing

                unset($dataErp, $itemArray);
                
            } catch (Exception $e) {
                    echo (string) $e->getMessage();
                }

        }
    }

    protected function pushCCOrder($order, $shipment='')
    {
        $collection        = Mage::getModel('sales/order')->loadByIncrementId($order->getIncrementId());
        $store             = Mage::app()->getStore();
        $storeTelephone    = Mage::getStoreConfig('general/store_information/phone');
        $storeAddress      = Mage::getStoreConfig('general/store_information/address');
        $shippingmethod    = $order->getShippingMethod();
        $paymentType       = 'CCOD';
        
        if ($collection->getData() && $shippingmethod == 'fetchr_fetchr' && $paymentType == 'CCOD' ) {
            $resource = Mage::getSingleton('core/resource');
            $adapter = $resource->getConnection('core_read');
            try {
                foreach ($order->getAllVisibleItems() as $item) {
                    if ($item['product_type'] == 'configurable') {
                        $itemArray[] = array(
                            'client_ref' => $order->getIncrementId(),
                            'name' => $item['name'],
                            'sku' => $item['sku'],
                            'quantity' => $item['qty_ordered'],
                            'merchant_details' => array(
                                'mobile' => $storeTelephone,
                                'phone' => $storeTelephone,
                                'name' => $store->getFrontendName(),
                                'address' => $storeAddress,
                            ),
                            'COD' => $order->getShippingAmount(),
                            'price' => $item['price'],
                            'is_voucher' => 'No',
                        );
                    } else {
                        $itemArray[] = array(
                            'client_ref' => $order->getIncrementId(),
                            'name' => $item['name'],
                            'sku' => $item['sku'],
                            'quantity' => $item['qty_ordered'],
                            'merchant_details' => array(
                                'mobile' => $storeTelephone,
                                'phone' => $storeTelephone,
                                'name' => $store->getFrontendName(),
                                'address' => $storeAddress,
                            ),
                            'COD' => $order->getShippingAmount(),
                            'price' => $item['price'],
                            'is_voucher' => 'No',
                        );
                    }
                }
                $discountAmount = 0;
                if ($order->getDiscountAmount()) {
                    $discountAmount = abs($order->getDiscountAmount());
                }
                
                $address        = $order->getShippingAddress()->getData();
                $grandtotal     = $order->getGrandTotal();
                $discount       = $discountAmount;

                $this->serviceType  = Mage::getStoreConfig('carriers/fetchr/servicetype');
                $this->userName     = Mage::getStoreConfig('carriers/fetchr/username');
                $this->password     = Mage::getStoreConfig('carriers/fetchr/password');
                $ServiceType        = $this->serviceType;
                
                switch ($ServiceType) {
                    case 'fulfilment':
                    $dataErp[] = array(
                        'order' => array(
                            'items' => $itemArray,
                            'details' => array(
                                'status' => '',
                                'discount' => $discount,
                                'grand_total' => $grandtotal,
                                'customer_email' => $order->getCustomerEmail(),
                                'order_id' => $order->getIncrementId(),
                                'customer_firstname' => $address['firstname'],
                                'payment_method' => $paymentType,
                                'customer_mobile' => ($address['telephone']?$address['telephone']:'N/A'),
                                'customer_lastname' => $address['lastname'],
                                'order_country' => $address['country_id'],
                                'order_address' => $address['street'].', '.$address['city'].', '.$address['country_id'],
                            ),
                        ),
                    );
                    break;
                    case 'delivery':
                    $dataErp = array(
                        'username' => $this->userName,
                        'password' => $this->password,
                        'method' => 'create_orders',
                        'pickup_location' => $storeAddress,
                        'data' => array(
                            array(
                                'order_reference' => $order->getIncrementId(),
                                'name' => $address['firstname'].' '.$address['lastname'],
                                'email' => $order->getCustomerEmail(),
                                'phone_number' => ($address['telephone']?$address['telephone']:'N/A'),
                                'address' => $address['street'],
                                'city' => $address['city'],
                                'payment_type' => $paymentType,
                                'amount' => $grandtotal,
                                'description' => 'No',
                                'comments' => 'No',
                            ),
                        ),
                    );
                }

                $result[$order->getIncrementId()]['request_data'] = $dataErp;
                $result[$order->getIncrementId()]['response_data'] = $this->_sendDataToErp($dataErp, $order->getIncrementId());
                
                $response = $result[$order->getIncrementId()]['response_data'];
                $comments = '';

                // Setting The Comment in the Order view
                if($ServiceType == 'fulfilment' ){

                    $tracking_number    = $response['response']['tracking_no'];
                    $response['status'] = ($response['success'] == true ? 'success' : 'faild');
                    
                    if($response['awb'] == 'SKU not found'){
                        $comments  .= '<strong>Fetchr Comment:</strong> One Of The SKUs Are Not Added to Fetchr System, Please Contact one of Fetchr\'s Account Managers for More Details';
                        $order->setStatus('pending');
                        $order->addStatusHistoryComment($comments, false);
                    }else{
                        $comments  .= '<strong>Fetchr Status : Tracking URL </strong> http://track.menavip.com/track.php?tracking_number='.$tracking_number;
                        $order->setStatus('processing');
                        $order->addStatusHistoryComment($comments, false);
                    }

                }elseif ($ServiceType == 'delivery') {
                    $tracking_number    = $response[key($response)];
                    $comments  .= '<strong>Fetchr Status : Tracking URL </strong> http://track.menavip.com/track.php?tracking_number='.$tracking_number;
                    $order->setStatus('processing');
                    $order->addStatusHistoryComment($comments, false);
                }

                //CCOD Order Shipping And Invoicing
                if( $response['status'] == 'success'){
                    try {
                        //Get Order Qty
                        $qty = array();
                        foreach ($order->getAllVisibleItems() as $item) {
                            $product_id             = $item->getProductId();
                            $Itemqty                = $item->getQtyOrdered() - $item->getQtyShipped() - $item->getQtyRefunded() - $item->getQtyCanceled();
                            $qty[$item->getId()]    = $Itemqty;
                        }

                        //Create Shipment When Auto Push Is OFF
                        if(!empty($shipment)){
                            //$shipment = $shipment->getData();
                            $trackdata = array();
                            $trackdata['carrier_code'] = 'fetchr';
                            $trackdata['title'] = 'Fetchr';
                            $trackdata['number'] = $tracking_number;
                            $track = Mage::getModel('sales/order_shipment_track')
                                ->setShipment($shipment)
                                ->setData('title', $trackdata['title']) // User syntax correct name here
                                ->setData('number', $trackdata['number'])
                                ->setData('carrier_code', $trackdata['carrier_code']) // use code that matches DB code for ship method here
                                ->setData('order_id', $shipment->getData('order_id'));

                            $track->save();
                        }

                        //Create Shipment When Auto Push Is On
                        if ($order->canShip()) {
                            $shipment = $order->prepareShipment($qty);

                            $trackdata = array();
                            $trackdata['carrier_code'] = 'fetchr';
                            $trackdata['title'] = 'Fetchr';
                            $trackdata['number'] = $tracking_number;
                            $track = Mage::getModel('sales/order_shipment_track')->addData($trackdata);
                            
                            $shipment->addTrack($track);
                            //$shipment->register();
                            $transactionSave = Mage::getModel('core/resource_transaction')
                            ->addObject($shipment)
                            ->addObject($shipment->getOrder())
                            ->save();

                            Mage::log('Order '.$orderId.' has been shipped!', null, 'fetchr.log');
                        } else {
                            Mage::log('Order '.$orderId.' cannot be shipped!', null, 'fetchr.log');
                        }
                        
                    }catch (Exception $e) {
                        $order->addStatusHistoryComment('Exception occurred during automaticallyInvoiceShipCompleteOrder action. Exception message: '.$e->getMessage(), false);
                        $order->save();
                    }  
                }
                //End COD Order Shipping And Invoicing

                unset($dataErp, $itemArray);
            } catch (Exception $e) {
                echo (string) $e->getMessage();
            }
        }
    }

    protected function _sendDataToErp($data, $orderId)
    {
        $response = null;
        
        try {
            $this->accountType  = Mage::getStoreConfig('carriers/fetchr/accounttype');
            $this->serviceType  = Mage::getStoreConfig('carriers/fetchr/servicetype');
            $this->userName     = Mage::getStoreConfig('carriers/fetchr/username');
            $this->password     = Mage::getStoreConfig('carriers/fetchr/password');

            $ServiceType = $this->serviceType;
            $accountType = $this->accountType;
            switch ($accountType) {
                case 'live':
                $baseurl = Mage::getStoreConfig('fetchr_shipping/settings/liveurl');
                break;
                case 'staging':
                $baseurl = Mage::getStoreConfig('fetchr_shipping/settings/stagingurl');
            }
            switch ($ServiceType) {
                case 'fulfilment':
                    $ERPdata        = 'ERPdata='.json_encode($data);
                    $merchant_name  = "MENA360 API";
                    $ch     = curl_init();
                    $url    = $baseurl.'/client/gapicurl/';
                    curl_setopt($ch, CURLOPT_URL, $url);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $ERPdata.'&erpuser='.$this->userName.'&erppassword='.$this->password.'&merchant_name='.$merchant_name);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    $response = curl_exec($ch);
                    curl_close($ch);

                    $decoded_response = json_decode($response, true);

                    // validate response
                    if(!is_array($decoded_response)){
                        return $response;
                    }

                    if ($response['response']['awb'] == 'SKU not found') {
                        $store = Mage::app()->getStore();
                        $cname = $store->getFrontendName();
                        $ch = curl_init();
                        $url = 'http://www.menavip.com/custom/smail.php';
                        curl_setopt($ch, CURLOPT_URL, $url);
                        curl_setopt($ch, CURLOPT_POST, true);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, 'orderId='.$orderId.'&cname='.$cname);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        $output = curl_exec($ch);
                        curl_close($ch);
                    }

                    if ($decoded_response['response']['tracking_no'] != '0') {
                        return $decoded_response;
                    }
                break;
                case 'delivery':
                    $data_string = 'args='.json_encode($data);
                    $ch = curl_init();
                    $url = $baseurl.'/client/api/';
                    curl_setopt($ch, CURLOPT_URL, $url);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    $response = curl_exec($ch);
                    curl_close($ch);

                    // validate response
                    $decoded_response   = json_decode($response, true);
                    if(!is_array($decoded_response)){
                        return $response;
                    }

                    $response = $decoded_response;

                    Mage::log('Order '.$orderId.' has been pushed!', null, 'fetchr.log');
                    Mage::log('Order data: '.print_r($data, true), null, 'fetchr.log');
                    return $response;
                break;    
            } 
        } catch (Exception $e) {
            echo (string) $e->getMessage();
        }
    }
}