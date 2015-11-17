<?php
/**
 * Fetchr
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
 * @package    Fetchr Shipping
 * Used in creating options for live|staging config value selection
 * @copyright  Copyright (c) 2015 Fetchr (http://www.fetchr.us)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Fetchr_Shipping_Model_Carrier extends Mage_Shipping_Model_Carrier_Abstract implements Mage_Shipping_Model_Carrier_Interface
{
  protected $_code = 'fetchr';
 
  public function collectRates(Mage_Shipping_Model_Rate_Request $request)
  {
    if (!Mage::getStoreConfig('carriers/'.$this->_code.'/active')) {
        return false;
    }
    $handling = Mage::getStoreConfig('carriers/'.$this->_code.'/handling');
    $result   = Mage::getModel('shipping/rate_result');
    $method   = Mage::getModel('shipping/rate_result_method');
    
    $method->setCarrier($this->_code);
    $method->setMethod($this->_code);
    $method->setCarrierTitle($this->getConfigData('title'));
    $method->setMethodTitle($this->getConfigData('name'));
    $method->setPrice('10');
    $method->setCost('10');
    $result->append($method);
 
    return $result;
  }
 
  public function getAllowedMethods()
  {
    return array(
      'fetchr' => $this->getConfigData('name'),
    );
  }
 
  protected function _getDefaultRate()
  {
    $rate = Mage::getModel('shipping/rate_result_method');
     
    $rate->setCarrier($this->_code);
    $rate->setCarrierTitle($this->getConfigData('title'));
    $rate->setMethod($this->_code);
    $rate->setMethodTitle($this->getConfigData('name'));
    $rate->setPrice($this->getConfigData('price'));
    $rate->setCost(0);
     
    return $rate;
  }

  public function isTrackingAvailable()
  {
      return true;
  }
}