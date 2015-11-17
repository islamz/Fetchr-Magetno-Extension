<?php
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