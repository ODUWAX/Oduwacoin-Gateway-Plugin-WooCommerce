<?php

class PaymentGateway
{
    private $public_key = "";
    private $private_key = "";
    private $boxID = "";
    private $coinLabel = "OWC";
    private $userID = "0";
    private $orderID = 0;
    private $coinAmount = 0;
    private $usdAmount = 0;
    private $boxwidth = 0;
    private $iframeID = "";
    private $cookieName = "";

    public function __construct($options = array())
    {
        if (!empty($options)) {
            if (version_compare(phpversion(), '5.4.0', '<')) die(sprintf("Error. You need PHP 5.4.0 (or greater). Current php version: %s", phpversion()));
            foreach ($options as $key => $value)
                if (in_array($key, array('public_key', 'private_key', 'boxID', 'coinLabel', 'userID', 'orderID', 'coinAmount', 'usdAmount', 'boxwidth', 'iframeID', 'cookieName'))) $this->$key = (is_string($value)) ? trim($value) : $value;
            if (preg_replace('/[^A-Za-z0-9\-]/', '', $this->public_key) != $this->public_key || strlen($this->public_key) != 36 || !strpos($this->public_key, "-") || !strpos($this->public_key, "PbK")) die("Invalid Cryptocoin Payment Box PUBLIC KEY - " . ($this->public_key ? $this->public_key : "cannot be empty"));
            if (preg_replace('/[^A-Za-z0-9\-]/', '', $this->private_key) != $this->private_key || strlen($this->private_key) != 37 || !strpos($this->private_key, "-") || !strpos($this->private_key, "PrVk")) die("Invalid Cryptocoin Payment Box PRIVATE KEY" . ($this->private_key ? "" : " - cannot be empty"));

            if ($this->iframeID && preg_replace('/[^A-Za-z0-9\_\-]/', '', $this->iframeID) != $this->iframeID) die("Invalid iframe ID - $this->iframeID. Allowed symbols: a..Z0..9_-");
            $this->userID = trim($this->userID);
            if ($this->userID && preg_replace('/[^A-Za-z0-9\.\_\-\@]/', '', $this->userID) != $this->userID) die("Invalid User ID - $this->userID. Allowed symbols: a..Z0..9_-@.");
            if (strlen($this->userID) > 50) die("Invalid User ID - $this->userID. Max: 50 symbols");
            $this->orderID = trim($this->orderID);
            if ($this->orderID && preg_replace('/[^A-Za-z0-9\.\_\-\@]/', '', $this->orderID) != $this->orderID) die("Invalid Order ID - $this->orderID. Allowed symbols: a..Z0..9_-@.");
            if (!$this->orderID || strlen($this->orderID) > 50) die("Invalid Order ID - $this->orderID. Max: 50 symbols");
            if (!$this->iframeID) $this->iframeID = $this->iframe_id();
            if (($this->usdAmount != 0 && $this->coinAmount >= 0.0001) || ($this->usdAmount == 0 && $this->coinAmount == 0)) {
                die('can\'t send USD and '.$this->coinLabel.' (1 amount must be 0(Zero))');
            }
            else if ($this->usdAmount != 0) {
                if ($this->usdAmount && strpos($this->usdAmount, ".")) $this->usdAmount = rtrim(rtrim($this->usdAmount, "0"), ".");
                if (!$this->usdAmount || $this->usdAmount <= 0.01) die("Invalid usdAmount - $this->usdAmount");
                if ($this->usdAmount && (!is_numeric($this->usdAmount) || $this->usdAmount < 0.01 || $this->usdAmount > 500000000)) die("Invalid USD Amount - " . sprintf('%.8f', $this->usdAmount) . " UDS. Allowed range: 0.01 .. 500,000,000");
            } else if ($this->coinAmount >=0.0001) {
                if ($this->coinAmount && strpos($this->coinAmount, ".")) $this->coinAmount = rtrim(rtrim($this->coinAmount, "0"), ".");
                if (!$this->coinAmount || $this->coinAmount <= 0.001) die("Invalid coinAmount - $this->coinAmount");
                if ($this->coinAmount && (!is_numeric($this->coinAmount) || $this->coinAmount < 0.0001 || $this->coinAmount > 500000000)) die("Invalid Coin Amount - " . sprintf('%.8f', $this->coinAmount) . " $this->coinLabel. Allowed range: 0.0001 .. 500,000,000");
            }
            $this->usdAmount = str_replace(",", "", number_format($this->usdAmount, 8));
            $this->coinAmount = str_replace(",", "", number_format($this->coinAmount, 8));

        }
        return true;
    }

    public function loadPaymentBox()
    {
        if ($this->cookieName != '') {
            $this->set_cookie();
        }
        $Iframe = '<iframe id="' . $this->iframeID . '" style="border-radius:15px;box-shadow:0 0 12px #aaa;-moz-box-shadow:0 0 12px #aaa;-webkit-box-shadow:0 0 12px #aaa;padding:3px;margin:10px;width:' . $this->boxwidth . 'px;height:185px;"
                scrolling="no" marginheight="0" marginwidth="0" frameborder="0"
                src="https://api.oduwagateway.com/payment-box/' . $this->boxID . '/' . $this->cryptobox_hash() . '/' . $this->coinLabel . '/' . $this->userID . '/' . $this->orderID . '/' . $this->coinAmount. '/' . $this->usdAmount . '/' . $this->iframeID . '/' . $this->boxwidth . '"></iframe>';
        return $Iframe;
    }


    public function cryptobox_hash()
    {
        $hash_str = $this->boxID . "|" . $this->public_key . "|" . $this->private_key . "|" . $this->coinAmount. "|" . $this->usdAmount . "|" . $this->orderID . "|" . $this->userID;
        $hash = md5($hash_str);
        return $hash;
    }

    public function iframe_id()
    {
        return "box" . $this->icrc32($this->boxID . "__" . $this->orderID . "__" . $this->userID . "__" . $this->private_key);
    }

    public function icrc32($str)
    {
        $in = crc32($str);
        $int_max = pow(2, 31) - 1;
        if ($in > $int_max) $out = $in - $int_max * 2 - 2;
        else $out = $in;
        $out = abs($out);

        return $out;
    }

    public function set_cookie()
    {
        $v = array(
            'boxID' => $this->boxID,
            'coinLabel' => $this->coinLabel,
            'userID' => $this->userID,
            'orderID' => $this->orderID,
            'coinAmount' => $this->coinAmount,
            'usdAmount' => $this->usdAmount,
            'boxwidth' => $this->boxwidth,
            'iframeID' => $this->iframeID,
        );
        setcookie($this->cookieName, base64_encode(json_encode($v)), time() + (10 * 365 * 24 * 60 * 60), '/', $this->check_server());
    }

    public function check_server()
    {
        $s = trim(strtolower($_SERVER['SERVER_NAME']), " /");
        if (stripos($s, "www.") === 0) $s = substr($s, 4);
        return $s;
    }
}

?>